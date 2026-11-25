<?php

namespace App\Services;

use App\Models\DineBill;
use App\Models\DineOrderItem;
use App\Models\Transaction;
use App\Support\Dine\DineSettlementContext;
use App\Support\Dine\DineSettlementResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DineBillSettlementService
{
    public function __construct(
        private readonly CashierShiftService $cashierShiftService,
        private readonly TaxService $taxService,
        private readonly DineBillService $billService,
        private readonly DineSessionService $sessionService,
    ) {}

    public function settleCash(
        DineBill $bill,
        int $userId,
        int $cashAmount,
        ?string $note = null
    ): DineSettlementResult {
        return $this->settle($bill, new DineSettlementContext(
            userId: $userId,
            isCashPayment: true,
            cashAmount: $cashAmount,
            note: $note,
        ));
    }

    public function settle(DineBill $bill, DineSettlementContext $context): DineSettlementResult
    {
        return DB::transaction(function () use ($bill, $context) {
            $shift = $this->cashierShiftService->requireActiveShiftForUser(
                $context->userId,
                lockForUpdate: true,
            );
            $shift->loadMissing('warehouse.outlet');

            $bill = DineBill::query()
                ->with(['session.table.area'])
                ->lockForUpdate()
                ->findOrFail($bill->id);

            if ($bill->status !== DineBill::STATUS_OPEN) {
                throw ValidationException::withMessages([
                    'bill' => 'Bill tidak dapat dibayar pada status '.$bill->status.'.',
                ]);
            }

            $this->sessionService->assertOutletAccess(
                $bill->session->table,
                $shift->warehouse?->outlet,
            );

            $items = $bill->items()
                ->with(['product', 'modifiers'])
                ->whereNull('voided_at')
                ->lockForUpdate()
                ->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages([
                    'bill' => 'Bill tidak memiliki item aktif.',
                ]);
            }

            $subtotal = (int) $items->sum(fn (DineOrderItem $item) => $item->price * $item->qty);
            $tax = $this->calculateTax($items);
            $discountTotal = 0;
            $grandTotal = $subtotal + $tax['exclusive_total'];
            $cashAmount = $context->cashAmount;
            $change = max(0, $cashAmount - $grandTotal);

            if ($context->isCashPayment && $cashAmount < $grandTotal) {
                throw ValidationException::withMessages([
                    'cash' => 'Uang tunai kurang dari total belanja.',
                ]);
            }

            $transaction = Transaction::create([
                'cashier_id' => $context->userId,
                'cashier_shift_id' => $shift->id,
                'warehouse_id' => $shift->warehouse_id,
                'customer_id' => $context->customer?->id,
                'invoice' => 'TRX-'.strtoupper(Str::random(10)),
                'cash' => $cashAmount,
                'change' => $change,
                'discount' => 0,
                'grand_total' => $grandTotal,
                'payment_method' => 'cash',
                'payment_status' => 'paid',
                'order_type' => 'in_store',
                'note' => $context->note,
                'tax_rate' => $tax['rate'],
                'tax_total' => $tax['total'],
            ]);

            $this->createDetails($transaction, $items);

            $bill->update([
                'status' => DineBill::STATUS_SETTLED,
                'settled_at' => now(),
                'transaction_id' => $transaction->id,
                'customer_id' => $context->customer?->id,
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'tax_total' => $tax['total'],
                'grand_total' => $grandTotal,
            ]);

            $this->billService->completeOrdersForBill($bill);
            $this->sessionService->closeIfIdle($bill->session);

            return new DineSettlementResult(
                transaction: $transaction->fresh(['customer']),
                bill: $bill->fresh(),
                subtotal: $subtotal,
                discountTotal: $discountTotal,
                taxTotal: $tax['total'],
                grandTotal: $grandTotal,
                needsDiscountApproval: false,
            );
        });
    }

    private function calculateTax($items): array
    {
        $total = 0;
        $exclusiveTotal = 0;
        $rate = 0.0;

        foreach ($items as $item) {
            $lineTotal = (int) $item->price * (int) $item->qty;
            $type = $item->tax_type ?? 'exclusive';
            $itemRate = (float) ($item->tax_rate ?? 0);
            $result = $this->taxService->calculateLineItem($lineTotal, $type, $itemRate);

            $total += $result['tax_amount'];

            if ($type !== 'inclusive') {
                $exclusiveTotal += $result['tax_amount'];
            }

            if ($itemRate > 0) {
                $rate = $itemRate;
            }
        }

        return [
            'total' => $total,
            'exclusive_total' => $exclusiveTotal,
            'rate' => $rate > 0 ? $rate : null,
        ];
    }

    private function createDetails(Transaction $transaction, $items): void
    {
        foreach ($items as $item) {
            $lineTotal = (int) $item->price * (int) $item->qty;

            $detail = $transaction->details()->create([
                'transaction_id' => $transaction->id,
                'product_id' => $item->product_id,
                'unit_id' => $item->unit_id,
                'conversion_factor' => $item->conversion_factor ?? 1,
                'qty' => (int) $item->qty,
                'base_unit_price' => (int) $item->price,
                'unit_price' => (int) $item->price,
                'price' => $lineTotal,
                'discount_total' => 0,
                'note' => $item->note,
            ]);

            $detail->modifiers()->createMany(
                $item->modifiers->map(fn ($modifier) => [
                    'modifier_id' => $modifier->modifier_id,
                    'modifier_group_id' => $modifier->modifier_group_id,
                    'group_name' => $modifier->group_name,
                    'name' => $modifier->name,
                    'price_delta' => $modifier->price_delta,
                ])->all()
            );

            $transaction->profits()->create([
                'transaction_id' => $transaction->id,
                'total' => $lineTotal - ((int) $item->unit_cost * (int) $item->qty),
            ]);
        }
    }
}
