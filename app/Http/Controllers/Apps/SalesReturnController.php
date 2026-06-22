<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSalesReturnRequest;
use App\Http\Requests\UpdateSalesReturnRequest;
use App\Models\CustomerCredit;
use App\Models\Profit;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Services\AuditLogService;
use App\Services\CashierShiftService;
use App\Services\StockMutationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SalesReturnController extends Controller
{
    public function __construct(
        private readonly StockMutationService $stockMutationService,
        private readonly CashierShiftService $cashierShiftService,
        private readonly AuditLogService $auditLogService
    ) {}

    public function index(Request $request): Response
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
            ->when(! $request->user()->isSuperAdmin(), function (Builder $query) use ($request) {
                $query->whereHas('transaction', function (Builder $builder) use ($request) {
                    $builder->where('cashier_id', $request->user()->id);
                });
            })
            ->when($filters['code'], fn (Builder $query, $code) => $query->where('code', 'like', '%'.$code.'%'))
            ->when($filters['invoice'], function (Builder $query, $invoice) {
                $query->whereHas('transaction', fn (Builder $builder) => $builder->where('invoice', 'like', '%'.$invoice.'%'));
            })
            ->when($filters['date_from'], fn (Builder $query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'], fn (Builder $query, $date) => $query->whereDate('created_at', '<=', $date))
            ->when($filters['return_type'], fn (Builder $query, $returnType) => $query->where('return_type', $returnType))
            ->withCount('items')
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Dashboard/SalesReturns/Index', [
            'salesReturns' => $salesReturns,
            'filters' => $filters,
        ]);
    }

    public function create(Request $request, Transaction $transaction): Response|RedirectResponse
    {
        $this->ensureSalesReturnTablesExist();

        $transaction = $this->resolveAccessibleTransaction($request, $transaction->id);

        if (! $this->transactionHasReturnableItems($transaction)) {
            return to_route('transactions.history')->with('error', 'Seluruh item transaksi ini sudah habis diretur.');
        }

        return Inertia::render('Dashboard/SalesReturns/Create', [
            'transaction' => $this->transformTransactionForEditor($transaction),
        ]);
    }

    public function store(StoreSalesReturnRequest $request, Transaction $transaction): RedirectResponse
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

        return to_route('sales-returns.show', $salesReturn)->with('success', 'Draft retur penjualan berhasil dibuat.');
    }

    public function show(Request $request, SalesReturn $salesReturn): Response
    {
        $this->ensureSalesReturnTablesExist();

        $salesReturn = $this->resolveAccessibleSalesReturn($request, $salesReturn->id);

        return Inertia::render('Dashboard/SalesReturns/Show', [
            'salesReturn' => $this->transformSalesReturn($salesReturn),
            'transaction' => $this->transformTransactionForEditor($salesReturn->transaction, $salesReturn),
        ]);
    }

    public function update(UpdateSalesReturnRequest $request, SalesReturn $salesReturn): RedirectResponse
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

        return back()->with('success', 'Draft retur penjualan berhasil diperbarui.');
    }

    public function complete(Request $request, SalesReturn $salesReturn): RedirectResponse
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
                if ($item->restock_to_inventory && $item->product) {
                    $product = $item->product()->lockForUpdate()->first();

                    if ($product) {
                        $stockBefore = (int) $product->stock;
                        $stockAfter = $stockBefore + (int) $item->qty_return;

                        $product->update([
                            'stock' => $stockAfter,
                        ]);

                        // Restock to transaction warehouse
                        $transactionWarehouseId = $salesReturn->transaction->warehouse_id;
                        if ($transactionWarehouseId) {
                            \App\Models\ProductWarehouse::where([
                                'product_id' => $product->id,
                                'warehouse_id' => $transactionWarehouseId,
                            ])->increment('stock', (int) $item->qty_return);
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

                $detail = $item->transactionDetail;
                $buyPrice = (int) ($item->product?->buy_price ?? 0);
                $margin = ((int) $detail->price - $buyPrice) * (int) $item->qty_return;

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

        return back()->with('success', 'Retur penjualan berhasil diselesaikan.');
    }

    private function salesReturnAuditPayload(SalesReturn $salesReturn): array
    {
        return [
            'code' => $salesReturn->code,
            'status' => $salesReturn->status,
            'return_type' => $salesReturn->return_type,
            'refund_amount' => (int) $salesReturn->refund_amount,
            'credited_amount' => (int) $salesReturn->credited_amount,
            'total_return_amount' => (int) $salesReturn->total_return_amount,
            'transaction_id' => (int) $salesReturn->transaction_id,
            'items_summary' => $salesReturn->items->map(fn (SalesReturnItem $item) => [
                'product_id' => $item->product_id,
                'product_title' => $item->product?->title,
                'qty_return' => (int) $item->qty_return,
                'subtotal_return' => (int) $item->subtotal_return,
                'restock_to_inventory' => (bool) $item->restock_to_inventory,
            ])->values()->all(),
        ];
    }

    private function resolveAccessibleTransaction(Request $request, int $transactionId): Transaction
    {
        return Transaction::query()
            ->with([
                'cashier:id,name',
                'customer:id,name',
                'receivable',
                'details.product:id,title,barcode,sku,buy_price',
                'details.salesReturnItems.salesReturn:id,status',
            ])
            ->when(! $request->user()->isSuperAdmin(), fn (Builder $query) => $query->where('cashier_id', $request->user()->id))
            ->findOrFail($transactionId);
    }

    private function resolveAccessibleSalesReturn(Request $request, int $salesReturnId): SalesReturn
    {
        return SalesReturn::query()
            ->with([
                'customer:id,name',
                'cashier:id,name',
                'transaction.cashier:id,name',
                'transaction.customer:id,name',
                'transaction.receivable',
                'transaction.details.product:id,title,barcode,sku,buy_price',
                'transaction.details.salesReturnItems.salesReturn:id,status',
                'items.product:id,title,barcode,sku,buy_price',
                'items.transactionDetail:id,transaction_id,product_id,qty,price',
            ])
            ->when(! $request->user()->isSuperAdmin(), function (Builder $query) use ($request) {
                $query->whereHas('transaction', fn (Builder $builder) => $builder->where('cashier_id', $request->user()->id));
            })
            ->findOrFail($salesReturnId);
    }

    private function transformTransactionForEditor(Transaction $transaction, ?SalesReturn $salesReturn = null): array
    {
        $draftItems = collect($salesReturn?->items ?? [])
            ->keyBy('transaction_detail_id');

        return [
            'id' => $transaction->id,
            'invoice' => $transaction->invoice,
            'created_at' => $transaction->getRawOriginal('created_at')
                ? \Carbon\Carbon::parse($transaction->getRawOriginal('created_at'))->toISOString()
                : null,
            'cashier' => $transaction->cashier ? [
                'id' => $transaction->cashier->id,
                'name' => $transaction->cashier->name,
            ] : null,
            'customer' => $transaction->customer ? [
                'id' => $transaction->customer->id,
                'name' => $transaction->customer->name,
            ] : null,
            'grand_total' => (int) $transaction->grand_total,
            'payment_method' => $transaction->payment_method,
            'payment_status' => $transaction->payment_status,
            'receivable' => $transaction->receivable ? [
                'id' => $transaction->receivable->id,
                'total' => (int) $transaction->receivable->total,
                'paid' => (int) $transaction->receivable->paid,
                'status' => $transaction->receivable->status,
                'remaining' => (int) $transaction->receivable->remaining,
            ] : null,
            'details' => $transaction->details->map(function (TransactionDetail $detail) use ($draftItems) {
                $completedReturnedQty = (int) $detail->salesReturnItems
                    ->filter(fn (SalesReturnItem $item) => $item->salesReturn?->status === 'completed')
                    ->sum('qty_return');

                $draftItem = $draftItems->get($detail->id);
                $qtySold = (int) $detail->qty;

                return [
                    'id' => $detail->id,
                    'product_id' => $detail->product_id,
                    'product' => $detail->product ? [
                        'id' => $detail->product->id,
                        'title' => $detail->product->title,
                        'barcode' => $detail->product->barcode,
                        'sku' => $detail->product->sku,
                    ] : null,
                    'qty' => $qtySold,
                    'price' => (int) $detail->price,
                    'returned_completed_qty' => $completedReturnedQty,
                    'remaining_returnable_qty' => max(0, $qtySold - $completedReturnedQty),
                    'draft_item' => $draftItem ? [
                        'qty_return' => (int) $draftItem->qty_return,
                        'return_reason' => $draftItem->return_reason,
                        'restock_to_inventory' => (bool) $draftItem->restock_to_inventory,
                        'subtotal' => (int) $draftItem->subtotal,
                    ] : null,
                ];
            })->values(),
        ];
    }

    private function transformSalesReturn(SalesReturn $salesReturn): array
    {
        return [
            'id' => $salesReturn->id,
            'code' => $salesReturn->code,
            'status' => $salesReturn->status,
            'return_type' => $salesReturn->return_type,
            'refund_amount' => (int) $salesReturn->refund_amount,
            'credited_amount' => (int) $salesReturn->credited_amount,
            'total_return_amount' => (int) $salesReturn->total_return_amount,
            'notes' => $salesReturn->notes,
            'created_at' => optional($salesReturn->created_at)?->toISOString(),
            'completed_at' => optional($salesReturn->completed_at)?->toISOString(),
            'cashier' => $salesReturn->cashier ? [
                'id' => $salesReturn->cashier->id,
                'name' => $salesReturn->cashier->name,
            ] : null,
            'customer' => $salesReturn->customer ? [
                'id' => $salesReturn->customer->id,
                'name' => $salesReturn->customer->name,
            ] : null,
            'transaction' => [
                'id' => $salesReturn->transaction?->id,
                'invoice' => $salesReturn->transaction?->invoice,
            ],
            'items' => $salesReturn->items->map(function (SalesReturnItem $item) {
                return [
                    'id' => $item->id,
                    'transaction_detail_id' => $item->transaction_detail_id,
                    'product' => $item->product ? [
                        'id' => $item->product->id,
                        'title' => $item->product->title,
                        'barcode' => $item->product->barcode,
                        'sku' => $item->product->sku,
                    ] : null,
                    'qty_sold' => (int) $item->qty_sold,
                    'qty_returned_before' => (int) $item->qty_returned_before,
                    'qty_return' => (int) $item->qty_return,
                    'unit_price' => (int) $item->unit_price,
                    'subtotal' => (int) $item->subtotal,
                    'return_reason' => $item->return_reason,
                    'restock_to_inventory' => (bool) $item->restock_to_inventory,
                ];
            })->values(),
        ];
    }

    private function transactionHasReturnableItems(Transaction $transaction): bool
    {
        return $transaction->details->contains(function (TransactionDetail $detail) {
            $completedReturnedQty = (int) $detail->salesReturnItems
                ->filter(fn (SalesReturnItem $item) => $item->salesReturn?->status === 'completed')
                ->sum('qty_return');

            return $completedReturnedQty < (int) $detail->qty;
        });
    }

    private function prepareDraftPayload(Transaction $transaction, array $validated, ?int $excludeSalesReturnId = null): array
    {
        $details = $transaction->details->keyBy('id');
        $returnedQtyMap = $this->getCompletedReturnedQtyMap($transaction->id, $excludeSalesReturnId);

        $returnType = $validated['return_type'];

        if (! $transaction->customer_id) {
            $returnType = 'refund_cash';
        }

        $items = collect($validated['items'])
            ->map(function (array $item) use ($details, $returnedQtyMap) {
                $detail = $details->get((int) $item['transaction_detail_id']);

                if (! $detail) {
                    throw ValidationException::withMessages([
                        'items' => 'Ada item retur yang tidak cocok dengan transaksi asal.',
                    ]);
                }

                $qtyReturn = (int) ($item['qty_return'] ?? 0);

                if ($qtyReturn < 1) {
                    return null;
                }

                $qtyReturnedBefore = (int) ($returnedQtyMap[$detail->id] ?? 0);
                $remainingQty = (int) $detail->qty - $qtyReturnedBefore;

                if ($qtyReturn > $remainingQty) {
                    throw ValidationException::withMessages([
                        'items' => 'Qty retur melebihi sisa qty yang bisa diretur.',
                    ]);
                }

                if (blank($item['return_reason'] ?? null)) {
                    throw ValidationException::withMessages([
                        'items' => 'Alasan retur wajib diisi untuk setiap item yang diretur.',
                    ]);
                }

                return [
                    'transaction_detail_id' => $detail->id,
                    'product_id' => $detail->product_id,
                    'qty_sold' => (int) $detail->qty,
                    'qty_returned_before' => $qtyReturnedBefore,
                    'qty_return' => $qtyReturn,
                    'unit_price' => (int) $detail->price,
                    'subtotal' => $qtyReturn * (int) $detail->price,
                    'return_reason' => trim($item['return_reason']),
                    'restock_to_inventory' => (bool) ($item['restock_to_inventory'] ?? true),
                ];
            })
            ->filter()
            ->values();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Pilih minimal satu item retur dengan qty lebih dari 0.',
            ]);
        }

        $totalReturnAmount = (int) $items->sum('subtotal');
        $settlement = $this->calculateSettlement($transaction, $totalReturnAmount, $returnType);

        return [
            'return_type' => $settlement['return_type'],
            'notes' => $validated['notes'] ?? null,
            'refund_amount' => $settlement['refund_amount'],
            'credited_amount' => $settlement['credited_amount'],
            'total_return_amount' => $totalReturnAmount,
            'items' => $items->all(),
        ];
    }

    private function calculateSettlement(Transaction $transaction, int $totalReturnAmount, string $returnType): array
    {
        $resolvedReturnType = ! $transaction->customer_id && $returnType === 'store_credit'
            ? 'refund_cash'
            : $returnType;

        $refundAmount = 0;
        $creditedAmount = 0;
        $receivableTotalAfter = null;

        if ($transaction->payment_method === 'pay_later' && $transaction->receivable) {
            $currentTotal = (int) $transaction->receivable->total;
            $paid = (int) $transaction->receivable->paid;
            $receivableTotalAfter = max(0, $currentTotal - $totalReturnAmount);
            $settlementAmount = max(0, $paid - $receivableTotalAfter);

            if ($resolvedReturnType === 'store_credit') {
                $creditedAmount = $settlementAmount;
            } else {
                $refundAmount = $settlementAmount;
            }
        } elseif ($transaction->payment_status === 'paid') {
            if ($resolvedReturnType === 'store_credit') {
                $creditedAmount = $totalReturnAmount;
            } else {
                $refundAmount = $totalReturnAmount;
            }
        }

        return [
            'return_type' => $resolvedReturnType,
            'refund_amount' => $refundAmount,
            'credited_amount' => $creditedAmount,
            'receivable_total_after' => $receivableTotalAfter,
        ];
    }

    private function determineReceivableStatus(int $total, int $paid, $dueDate): string
    {
        if ($paid >= $total) {
            return 'paid';
        }

        if ($paid > 0) {
            return 'partial';
        }

        if ($dueDate && now()->startOfDay()->gt($dueDate->copy()->startOfDay())) {
            return 'overdue';
        }

        return 'unpaid';
    }

    private function getCompletedReturnedQtyMap(int $transactionId, ?int $excludeSalesReturnId = null): Collection
    {
        return SalesReturnItem::query()
            ->selectRaw('transaction_detail_id, COALESCE(SUM(qty_return), 0) as total_qty')
            ->whereHas('salesReturn', function (Builder $query) use ($transactionId, $excludeSalesReturnId) {
                $query->where('transaction_id', $transactionId)
                    ->where('status', 'completed');

                if ($excludeSalesReturnId) {
                    $query->where('id', '!=', $excludeSalesReturnId);
                }
            })
            ->groupBy('transaction_detail_id')
            ->pluck('total_qty', 'transaction_detail_id');
    }

    private function ensureDraft(SalesReturn $salesReturn): void
    {
        if (! $salesReturn->isDraft()) {
            throw ValidationException::withMessages([
                'sales_return' => 'Retur penjualan yang sudah selesai tidak dapat diubah lagi.',
            ]);
        }
    }

    private function ensureSalesReturnTablesExist(): void
    {
        if (! Schema::hasTable('sales_returns') || ! Schema::hasTable('sales_return_items')) {
            abort(503, 'Fitur retur penjualan belum siap. Jalankan migrasi database terlebih dahulu.');
        }
    }

    private function generateCode(): string
    {
        do {
            $code = 'SR-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));
        } while (SalesReturn::where('code', $code)->exists());

        return $code;
    }
}
