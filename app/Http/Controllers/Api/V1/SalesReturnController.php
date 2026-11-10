<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Apps\SalesReturnController as AppsSalesReturnController;
use App\Http\Requests\StoreSalesReturnRequest;
use App\Http\Requests\UpdateSalesReturnRequest;
use App\Http\Traits\ApiResponder;
use App\Models\CustomerCredit;
use App\Models\ProductWarehouse;
use App\Models\Profit;
use App\Models\SalesReturn;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesReturnController extends AppsSalesReturnController
{
    use ApiResponder;

    /**
     * GET /api/v1/sales-returns
     * Paginated sales return list with filters.
     */
    public function listReturns(Request $request): JsonResponse
    {
        $this->ensureSalesReturnTablesExist();

        $filters = [
            'code' => $request->input('code'),
            'invoice' => $request->input('invoice'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'return_type' => $request->input('return_type'),
        ];

        $salesReturns = SalesReturn::query()
            ->with(['transaction:id,invoice,payment_method,payment_status', 'customer:id,name', 'cashier:id,name'])
            ->when(! $request->user()->isSuperAdmin(), function ($query) use ($request) {
                $query->whereHas('transaction', function ($builder) use ($request) {
                    $builder->where('cashier_id', $request->user()->id);
                });
            })
            ->when($filters['code'], fn ($query, $code) => $query->where('code', 'like', '%'.$code.'%'))
            ->when($filters['invoice'], function ($query, $invoice) {
                $query->whereHas('transaction', fn ($builder) => $builder->where('invoice', 'like', '%'.$invoice.'%'));
            })
            ->when($filters['date_from'], fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'], fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->when($filters['return_type'], fn ($query, $returnType) => $query->where('return_type', $returnType))
            ->withCount('items')
            ->latest()
            ->paginate($this->perPage())->withQueryString();

        return $this->paginated($salesReturns);
    }

    /**
     * GET /api/v1/sales-returns/{salesReturn}
     * Return detail with transaction and items.
     */
    public function showReturn(Request $request, SalesReturn $salesReturn): JsonResponse
    {
        $this->ensureSalesReturnTablesExist();

        $salesReturn = $this->resolveAccessibleSalesReturn($request, $salesReturn->id);

        return $this->ok($this->transformSalesReturn($salesReturn));
    }

    /**
     * GET /api/v1/sales-returns/create/{transaction}
     * Return-able transaction data for the create form.
     */
    public function createFor(Request $request, Transaction $transaction): JsonResponse
    {
        $this->ensureSalesReturnTablesExist();

        $transaction = $this->resolveAccessibleTransaction($request, $transaction->id);

        if (! $this->transactionHasReturnableItems($transaction)) {
            return $this->error('Seluruh item transaksi ini sudah habis diretur.', 422);
        }

        return $this->ok($this->transformTransactionForEditor($transaction));
    }

    /**
     * POST /api/v1/sales-returns
     * Create a draft sales return.
     */
    public function createReturn(StoreSalesReturnRequest $request, Transaction $transaction): JsonResponse
    {
        $this->ensureSalesReturnTablesExist();

        $transaction = $this->resolveAccessibleTransaction($request, $transaction->id);
        $payload = $this->prepareDraftPayload($transaction, $request->validated());

        $salesReturn = DB::transaction(function () use ($request, $transaction, $payload) {
            $salesReturn = SalesReturn::create([
                'code' => $this->generateCode(),
                'transaction_id' => $transaction->id,
                'customer_id' => $transaction->customer_id,
                'cashier_id' => $request->user()?->id,
                'status' => 'draft',
                'return_type' => $payload['return_type'],
                'refund_amount' => $payload['refund_amount'],
                'credited_amount' => $payload['credited_amount'],
                'total_return_amount' => $payload['total_return_amount'],
                'notes' => $payload['notes'],
            ]);

            $salesReturn->items()->createMany($payload['items']);

            return $salesReturn;
        });

        $salesReturn->load('items.product');
        $this->auditLogService->log(
            event: 'sales_return.created',
            module: 'sales_returns',
            auditable: $salesReturn,
            description: 'Draft retur penjualan dibuat.',
            after: $this->salesReturnAuditPayload($salesReturn),
        );

        return $this->created($this->transformSalesReturn($salesReturn), 'Draft retur penjualan berhasil dibuat.');
    }

    /**
     * PUT /api/v1/sales-returns/{salesReturn}
     * Update a draft sales return.
     */
    public function updateReturn(UpdateSalesReturnRequest $request, SalesReturn $salesReturn): JsonResponse
    {
        $this->ensureSalesReturnTablesExist();

        $salesReturn = $this->resolveAccessibleSalesReturn($request, $salesReturn->id);
        $this->ensureDraft($salesReturn);
        $before = $this->salesReturnAuditPayload($salesReturn);

        $payload = $this->prepareDraftPayload($salesReturn->transaction, $request->validated(), $salesReturn->id);

        DB::transaction(function () use ($salesReturn, $payload) {
            $salesReturn->update([
                'return_type' => $payload['return_type'],
                'refund_amount' => $payload['refund_amount'],
                'credited_amount' => $payload['credited_amount'],
                'total_return_amount' => $payload['total_return_amount'],
                'notes' => $payload['notes'],
            ]);

            $salesReturn->items()->delete();
            $salesReturn->items()->createMany($payload['items']);
        });

        $salesReturn->refresh();
        $salesReturn->load('items.product');
        $this->auditLogService->log(
            event: 'sales_return.updated',
            module: 'sales_returns',
            auditable: $salesReturn,
            description: 'Draft retur penjualan diperbarui.',
            before: $before,
            after: $this->salesReturnAuditPayload($salesReturn),
        );

        return $this->ok($this->transformSalesReturn($salesReturn), 'Draft retur penjualan berhasil diperbarui.');
    }

    /**
     * POST /api/v1/sales-returns/{salesReturn}/complete
     * Complete a draft sales return, applying stock/finance effects.
     */
    public function completeReturn(Request $request, SalesReturn $salesReturn): JsonResponse
    {
        $this->ensureSalesReturnTablesExist();

        $salesReturn = $this->resolveAccessibleSalesReturn($request, $salesReturn->id);
        $this->ensureDraft($salesReturn);
        $before = $this->salesReturnAuditPayload($salesReturn);

        DB::transaction(function () use ($request, $salesReturn) {
            $activeShift = $this->cashierShiftService->requireActiveShiftForUser(
                $request->user()->id,
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

            $returnedQtyMap = $this->getCompletedReturnedQtyMap(
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
                $detail = $item->transactionDetail;
                $baseQty = (int) round((int) $item->qty_return * (float) ($detail->conversion_factor ?? 1));

                if ($item->restock_to_inventory && $item->product) {
                    $product = $item->product()->lockForUpdate()->first();

                    if ($product) {
                        $stockBefore = (int) $product->stock;
                        $stockAfter = $stockBefore + $baseQty;

                        $product->update(['stock' => $stockAfter]);

                        if ($transactionWarehouseId = $salesReturn->transaction->warehouse_id) {
                            ProductWarehouse::where([
                                'product_id' => $product->id,
                                'warehouse_id' => $transactionWarehouseId,
                            ])->increment('stock', $baseQty);
                        }

                        $this->stockMutationService->recordSalesReturnRestock(
                            product: $product,
                            salesReturn: $salesReturn,
                            stockBefore: $stockBefore,
                            stockAfter: $stockAfter,
                            reason: $item->return_reason,
                            userId: $request->user()?->id,
                        );
                    }
                }

                $buyPrice = (int) ($item->product?->buy_price ?? 0);
                $unitSellPrice = (int) round((int) $detail->price / max(1, (int) $detail->qty));
                $unitCost = (int) round($buyPrice * (float) ($detail->conversion_factor ?? 1));
                $margin = ($unitSellPrice - $unitCost) * (int) $item->qty_return;

                Profit::create([
                    'transaction_id' => $salesReturn->transaction_id,
                    'total' => -$margin,
                ]);
            }

            $salesReturn->loadMissing('transaction.receivable');
            $settlement = $this->calculateSettlement(
                $salesReturn->transaction,
                (int) $salesReturn->total_return_amount,
                $salesReturn->return_type
            );

            $salesReturn->update([
                'cashier_shift_id' => $activeShift->id,
                'warehouse_id' => $salesReturn->transaction->warehouse_id,
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
                        'status' => $this->determineReceivableStatus(
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
            after: $this->salesReturnAuditPayload($salesReturn),
        );

        return $this->ok($this->transformSalesReturn($salesReturn), 'Retur penjualan berhasil diselesaikan.');
    }
}
