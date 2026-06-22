<?php

declare(strict_types=1);

namespace App\Services\SalesReturns;

use App\Models\CustomerCredit;
use App\Models\Profit;
use App\Models\SalesReturn;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\CashierShiftService;
use App\Services\StockMutationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompleteSalesReturnService
{
    public function __construct(
        private readonly SalesReturnGuardService $guard,
        private readonly SalesReturnAccessService $access,
        private readonly SalesReturnPayloadService $payloadService,
        private readonly SalesReturnTransformerService $transformer,
        private readonly StockMutationService $stockMutationService,
        private readonly CashierShiftService $cashierShiftService,
        private readonly AuditLogService $auditLogService
    ) {}

    public function execute(SalesReturn $salesReturn, User $user): void
    {
        $this->guard->ensureTablesExist();

        $salesReturn = $this->access->resolveAccessibleSalesReturn($user, $salesReturn->id);
        $this->guard->ensureDraft($salesReturn);
        $before = $this->transformer->auditPayload($salesReturn);

        DB::transaction(function () use ($user, $salesReturn) {
            $activeShift = $this->cashierShiftService->requireActiveShiftForUser(
                $user->id,
                lockForUpdate: true
            );

            $salesReturn->load([
                'transaction.receivable',
                'items.product',
                'items.transactionDetail',
            ]);

            if ($salesReturn->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'sales_return' => 'Draft retur belum memiliki item.',
                ]);
            }

            $returnedQtyMap = $this->payloadService->completedReturnedQtyMap(
                $salesReturn->transaction_id,
                excludeSalesReturnId: $salesReturn->id,
            );

            foreach ($salesReturn->items as $item) {
                $detail = $item->transactionDetail;

                if (! $detail || $item->qty_return < 1) {
                    throw ValidationException::withMessages([
                        'sales_return' => 'Seluruh item retur harus memiliki kuantitas minimal 1.',
                    ]);
                }

                $returnedBefore = (int) ($returnedQtyMap[$detail->id] ?? 0);
                $remainingQty = (int) $detail->qty - $returnedBefore;

                if ($item->qty_return > $remainingQty) {
                    throw ValidationException::withMessages([
                        'sales_return' => 'Ada item retur yang melebihi sisa qty yang bisa diretur.',
                    ]);
                }
            }

            foreach ($salesReturn->items as $item) {
                if ($item->restock_to_inventory && $item->product) {
                    $product = $item->product()->lockForUpdate()->first();

                    if ($product) {
                        $stockBefore = (int) $product->stock;
                        $stockAfter = $stockBefore + (int) $item->qty_return;

                        $product->update([
                            'stock' => $stockAfter,
                        ]);

                        $this->stockMutationService->recordSalesReturnRestock(
                            product: $product,
                            salesReturn: $salesReturn,
                            stockBefore: $stockBefore,
                            stockAfter: $stockAfter,
                            reason: $item->return_reason,
                            userId: $user->id,
                        );
                    }
                }

                $detail = $item->transactionDetail;
                $buyPrice = (int) ($item->product?->buy_price ?? 0);
                $margin = ((int) $detail->price - $buyPrice) * (int) $item->qty_return;

                Profit::create([
                    'transaction_id' => $salesReturn->transaction_id,
                    'total' => -$margin,
                ]);
            }

            $salesReturn->loadMissing('transaction.receivable');
            $settlement = $this->payloadService->calculateSettlement(
                $salesReturn->transaction,
                (int) $salesReturn->total_return_amount,
                $salesReturn->return_type
            );

            $salesReturn->update([
                'cashier_shift_id' => $activeShift->id,
                'return_type' => $settlement['return_type'],
                'refund_amount' => $settlement['refund_amount'],
                'credited_amount' => $settlement['credited_amount'],
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            if ($salesReturn->transaction->payment_method === 'pay_later' && $salesReturn->transaction->receivable) {
                $receivable = $salesReturn->transaction->receivable()->lockForUpdate()->first();

                if ($receivable) {
                    $receivable->update([
                        'total' => $settlement['receivable_total_after'],
                        'status' => $this->payloadService->determineReceivableStatus(
                            total: $settlement['receivable_total_after'],
                            paid: (int) $receivable->paid,
                            dueDate: $receivable->due_date,
                        ),
                    ]);
                }
            }

            if (
                $salesReturn->return_type === 'store_credit'
                && $salesReturn->customer_id
                && $salesReturn->credited_amount > 0
            ) {
                CustomerCredit::create([
                    'customer_id' => $salesReturn->customer_id,
                    'sales_return_id' => $salesReturn->id,
                    'amount' => $salesReturn->credited_amount,
                    'balance' => $salesReturn->credited_amount,
                    'notes' => 'Saldo toko dari retur penjualan '.$salesReturn->code,
                ]);
            }
        });

        $salesReturn->refresh();
        $salesReturn->load('items.product');
        $this->auditLogService->log(
            event: 'sales_return.completed',
            module: 'sales_returns',
            auditable: $salesReturn,
            description: 'Retur penjualan diselesaikan.',
            before: $before,
            after: $this->transformer->auditPayload($salesReturn),
        );
    }
}
