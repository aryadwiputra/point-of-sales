<?php

namespace App\Services;

use App\Exceptions\PaymentGatewayException;
use App\Models\DineBill;
use App\Models\DineOrderItem;
use App\Models\DiscountApprovalLog;
use App\Models\Outlet;
use App\Models\PaymentSetting;
use App\Models\Receivable;
use App\Models\Transaction;
use App\Models\TransactionTender;
use App\Services\Payments\PaymentGatewayManager;
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
        private readonly LoyaltyService $loyaltyService,
        private readonly TransactionTenderService $tenderService,
        private readonly PaymentGatewayManager $paymentGatewayManager,
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
            $outlet = $shift->warehouse?->outlet;

            $bill = DineBill::query()
                ->with(['session.table.area'])
                ->lockForUpdate()
                ->findOrFail($bill->id);

            if ($bill->status !== DineBill::STATUS_OPEN) {
                throw ValidationException::withMessages([
                    'bill' => 'Bill tidak dapat dibayar pada status '.$bill->status.'.',
                ]);
            }

            $this->sessionService->assertOutletAccess($bill->session->table, $outlet);

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
            $preview = $this->discountPreview($items, $subtotal, $context, $outlet);
            $discountTotal = (int) data_get($preview, 'summary.voucher_discount_total', 0)
                + (int) data_get($preview, 'summary.loyalty_discount_total', 0)
                + (int) data_get($preview, 'summary.manual_discount_total', 0);
            $tax = $this->calculateTax($items);
            $grandTotal = max(0, $subtotal - $discountTotal) + $tax['exclusive_total'];

            $tenders = $context->useTenders
                ? $this->tenderService->normalize($context->tenderInput, $grandTotal, allowEmpty: false, outlet: $outlet)
                : [];

            $tenderCash = (int) collect($tenders)->where('method', TransactionTender::METHOD_CASH)->sum('cash_received');
            $tenderChange = (int) collect($tenders)->sum('change');
            $paymentMethod = $context->useTenders
                ? (count($tenders) > 1 ? 'split' : $tenders[0]['method'])
                : ($context->isPayLater ? 'pay_later' : ($context->paymentGateway ?: 'cash'));
            $paymentStatus = $context->useTenders
                ? $this->tenderService->aggregateStatus($tenders)
                : ($context->isCashPayment ? 'paid' : ($context->isPayLater ? 'unpaid' : 'pending'));
            $tenderBankAccountId = $context->useTenders
                ? (collect($tenders)->firstWhere('bank_account_id', '!==', null)['bank_account_id'] ?? null)
                : ($context->paymentGateway === 'bank_transfer' ? $context->bankAccountId : null);

            if ($context->isCashPayment && $context->cashAmount < $grandTotal) {
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
                'cash' => $context->useTenders ? $tenderCash : ($context->isCashPayment ? $context->cashAmount : 0),
                'change' => $context->useTenders ? $tenderChange : max(0, $context->cashAmount - $grandTotal),
                'discount' => (int) data_get($preview, 'summary.manual_discount_total', 0),
                'loyalty_points_redeemed' => (int) data_get($preview, 'summary.applied_redeem_points', 0),
                'loyalty_discount_total' => (int) data_get($preview, 'summary.loyalty_discount_total', 0),
                'customer_voucher_discount' => (int) data_get($preview, 'summary.voucher_discount_total', 0),
                'customer_voucher_code' => data_get($preview, 'voucher.code'),
                'customer_voucher_name' => data_get($preview, 'voucher.name'),
                'grand_total' => $grandTotal,
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus,
                'bank_account_id' => $tenderBankAccountId,
                'order_type' => 'in_store',
                'note' => $context->note,
                'tax_rate' => $tax['rate'],
                'tax_total' => $tax['total'],
            ]);

            if ($context->useTenders) {
                $transaction->tenders()->createMany($tenders);
            }

            $this->createDetails($transaction, $items);

            if ($context->isPayLater) {
                Receivable::create([
                    'customer_id' => $context->customer?->id,
                    'transaction_id' => $transaction->id,
                    'invoice' => $transaction->invoice,
                    'total' => $grandTotal,
                    'paid' => 0,
                    'due_date' => $context->dueDate,
                    'status' => 'unpaid',
                ]);
            }

            $this->loyaltyService->finalizeTransaction($transaction, $context->customer, $preview);

            $needsApproval = $transaction->discount > 0 && $transaction->needsDiscountApproval();

            if ($needsApproval) {
                $transaction->update([
                    'discount_approval_status' => 'pending',
                    'payment_status' => 'pending_approval',
                ]);

                DiscountApprovalLog::create([
                    'transaction_id' => $transaction->id,
                    'cashier_id' => $context->userId,
                    'requested_discount' => (int) $transaction->discount,
                    'status' => 'pending',
                ]);
            }

            if (! $needsApproval) {
                $this->createGatewayPayments(
                    $transaction,
                    $context,
                    PaymentSetting::forOutlet($outlet),
                    $tenders,
                );
            }

            $bill->update([
                'status' => $needsApproval ? DineBill::STATUS_PENDING_APPROVAL : DineBill::STATUS_SETTLED,
                'settled_at' => $needsApproval ? null : now(),
                'transaction_id' => $transaction->id,
                'customer_id' => $context->customer?->id,
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'tax_total' => $tax['total'],
                'grand_total' => $grandTotal,
            ]);

            if (! $needsApproval) {
                $this->billService->completeOrdersForBill($bill);
                $this->sessionService->closeIfIdle($bill->session);
            }

            return new DineSettlementResult(
                transaction: $transaction->fresh(['customer', 'tenders']),
                bill: $bill->fresh(),
                subtotal: $subtotal,
                discountTotal: $discountTotal,
                taxTotal: $tax['total'],
                grandTotal: $grandTotal,
                needsDiscountApproval: $needsApproval,
            );
        });
    }

    private function discountPreview(
        $items,
        int $subtotal,
        DineSettlementContext $context,
        ?Outlet $outlet
    ): array {
        if (
            ! $context->customer
            && ! $context->voucher
            && $context->manualDiscount === 0
            && $context->requestedRedeemPoints === 0
        ) {
            return [
                'summary' => [
                    'voucher_discount_total' => 0,
                    'loyalty_discount_total' => 0,
                    'manual_discount_total' => 0,
                    'applied_redeem_points' => 0,
                ],
                'voucher' => null,
            ];
        }

        $pricingPreview = [
            'items' => $items->map(fn (DineOrderItem $item) => [
                'product_id' => $item->product_id,
                'qty' => (int) $item->qty,
                'base_unit_price' => (int) $item->price,
                'effective_unit_price' => (int) $item->price,
                'line_total' => (int) $item->price * (int) $item->qty,
            ])->all(),
            'summary' => [
                'base_subtotal' => $subtotal,
                'promo_discount_total' => 0,
                'subtotal_after_promo' => $subtotal,
            ],
        ];

        return $this->loyaltyService->previewCheckout(
            $pricingPreview,
            $context->customer,
            [
                'manual_discount' => $context->manualDiscount,
                'shipping_cost' => 0,
                'redeem_points' => $context->requestedRedeemPoints,
                'voucher' => $context->voucher,
            ],
            null,
            $outlet,
        );
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

    private function createGatewayPayments(
        Transaction $transaction,
        DineSettlementContext $context,
        ?PaymentSetting $setting,
        array $tenders
    ): void {
        if ($context->isPayLater) {
            return;
        }

        try {
            if ($context->useTenders) {
                $gatewayTenders = $transaction->tenders()
                    ->whereIn('method', [
                        TransactionTender::METHOD_MIDTRANS,
                        TransactionTender::METHOD_XENDIT,
                        TransactionTender::METHOD_QRIS,
                    ])
                    ->where('payment_status', TransactionTender::STATUS_PENDING)
                    ->get();

                foreach ($gatewayTenders as $tender) {
                    $response = $this->paymentGatewayManager->createTenderPayment(
                        $transaction,
                        $tender,
                        $setting ?? PaymentSetting::forOutlet(null),
                    );

                    $tender->update([
                        'payment_reference' => $response['reference'] ?? null,
                        'payment_url' => $response['payment_url'] ?? null,
                        'qr_string' => $response['qr_string'] ?? null,
                    ]);
                }

                if ($gatewayTenders->count() === 1) {
                    $transaction->update(
                        $gatewayTenders->first()->only(['payment_reference', 'payment_url', 'qr_string'])
                    );
                }

                return;
            }

            if (! $context->paymentGateway) {
                return;
            }

            if (! $setting) {
                throw ValidationException::withMessages([
                    'payment_gateway' => 'Gateway pembayaran belum dikonfigurasi.',
                ]);
            }

            $ready = $context->paymentGateway === 'qris'
                ? $setting->isGatewayReady(PaymentSetting::GATEWAY_MIDTRANS)
                    || $setting->isGatewayReady(PaymentSetting::GATEWAY_XENDIT)
                : $setting->isGatewayReady($context->paymentGateway);

            if (! $ready) {
                throw ValidationException::withMessages([
                    'payment_gateway' => 'Gateway pembayaran belum dikonfigurasi.',
                ]);
            }

            $response = $context->paymentGateway === 'qris'
                ? $this->paymentGatewayManager->createQrisPayment($transaction, $setting)
                : $this->paymentGatewayManager->createPayment($transaction, $context->paymentGateway, $setting);

            $transaction->update([
                'payment_reference' => $response['reference'] ?? null,
                'payment_url' => $response['payment_url'] ?? null,
                'qr_string' => $response['qr_string'] ?? null,
            ]);
        } catch (PaymentGatewayException $exception) {
            throw ValidationException::withMessages([
                'payment_gateway' => $exception->getMessage(),
            ]);
        }
    }
}
