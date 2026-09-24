<?php

namespace App\Services;

use App\Models\CashierShift;
use App\Models\DineOrder;
use App\Models\ProductBatch;
use App\Models\ProductWarehouse;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DineOrderService
{
    public function __construct(
        private PricingService $pricingService,
        private StockMutationService $stockMutationService,
    ) {}

    public function accept(DineOrder $order): void
    {
        $cashierId = $order->cashier_id ?? auth()->id();
        $shift = CashierShift::with('warehouse.outlet')
            ->where('user_id', $cashierId)
            ->open()
            ->first();

        // ponytail: without an open shift there is no warehouse context to take stock from — refuse instead of accepting an order whose stock is never decremented
        if (! $shift) {
            throw ValidationException::withMessages([
                'shift' => 'Tidak ada shift kasir aktif. Buka shift terlebih dahulu sebelum menerima pesanan.',
            ]);
        }

        $warehouseId = $shift->warehouse_id;

        DB::transaction(function () use ($order, $shift, $warehouseId, $cashierId) {
            // ponytail: lock the order row so accept cannot race with a second accept/reject
            $order = DineOrder::with(['items.product.components', 'table.area.outlet'])
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $tableOutletId = $order->table?->area?->outlet_id;
            $shiftOutletId = $shift->warehouse?->outlet_id;

            if ($tableOutletId && $shiftOutletId && $tableOutletId !== $shiftOutletId) {
                throw ValidationException::withMessages([
                    'shift' => 'Shift kasir harus berada di outlet yang sama dengan meja.',
                ]);
            }

            $userId = $cashierId;

            // ponytail: accepting a dine order is a sale — record a Transaction so revenue, profit, stock ledger and stock mutations all stay in sync with the POS path
            $transaction = Transaction::create([
                'cashier_id' => $userId,
                'cashier_shift_id' => $shift->id,
                'warehouse_id' => $warehouseId,
                'customer_id' => $order->customer_id,
                'invoice' => 'TRX-'.strtoupper(Str::random(10)),
                'cash' => 0,
                'change' => 0,
                'discount' => 0,
                'grand_total' => (int) $order->subtotal,
                'payment_method' => 'cash',
                'payment_status' => 'unpaid',
                'order_type' => 'in_store',
                'note' => $order->notes,
            ]);

            foreach ($order->items as $item) {
                $product = $item->product;
                $lineTotal = (int) $item->price * (int) $item->qty;

                $detail = $transaction->details()->create([
                    'transaction_id' => $transaction->id,
                    'product_id' => $product->id,
                    'unit_id' => $item->unit_id,
                    'conversion_factor' => $item->conversion_factor ?? 1,
                    'qty' => (int) $item->qty,
                    'base_unit_price' => (int) $item->price,
                    'unit_price' => (int) $item->price,
                    'price' => $lineTotal,
                    'discount_total' => 0,
                ]);

                if ($product->is_composite) {
                    $totalBuyPrice = $product->components->sum(
                        fn ($component) => $component->buy_price * (float) $component->pivot->qty
                    ) * (int) $item->qty;
                } else {
                    $totalBuyPrice = $product->buy_price * (int) $item->qty * (float) ($item->conversion_factor ?? 1);
                }

                $transaction->profits()->create([
                    'transaction_id' => $transaction->id,
                    'total' => $lineTotal - (int) $totalBuyPrice,
                ]);

                if ($product->is_composite) {
                    foreach ($product->components as $component) {
                        $componentQty = (int) round((float) $component->pivot->qty * (int) $item->qty);

                        $pw = ProductWarehouse::where('product_id', $component->id)
                            ->where('warehouse_id', $warehouseId)
                            ->lockForUpdate()
                            ->first();

                        $available = $pw ? (int) $pw->stock : (int) $component->stock;

                        if ($available < $componentQty) {
                            throw ValidationException::withMessages([
                                'stock' => "Stok komponen {$component->title} tidak mencukupi (tersedia: {$available}).",
                            ]);
                        }

                        $pw?->decrement('stock', $componentQty);
                        $component->decrement('stock', $componentQty);

                        $this->stockMutationService->recordMutation(
                            product: $component,
                            warehouseId: $warehouseId,
                            referenceType: 'dine_order',
                            referenceId: $order->id,
                            mutationType: 'out',
                            qty: $componentQty,
                            stockBefore: $available,
                            stockAfter: $available - $componentQty,
                            notes: "Pesanan dine-in #{$order->id} (komponen komposit {$product->title})",
                            userId: $userId,
                        );
                    }
                } else {
                    $baseQty = $item->baseQuantity();

                    $pw = ProductWarehouse::where('product_id', $product->id)
                        ->where('warehouse_id', $warehouseId)
                        ->lockForUpdate()
                        ->first();

                    $available = $pw ? (int) $pw->stock : (int) $product->stock;

                    if ($available < $baseQty) {
                        throw ValidationException::withMessages([
                            'stock' => "Stok {$product->title} tidak mencukupi (tersedia: {$available}).",
                        ]);
                    }

                    $pw?->decrement('stock', $baseQty);
                    $product->decrement('stock', $baseQty);

                    $this->stockMutationService->recordMutation(
                        product: $product,
                        warehouseId: $warehouseId,
                        referenceType: 'dine_order',
                        referenceId: $order->id,
                        mutationType: 'out',
                        qty: $baseQty,
                        stockBefore: $available,
                        stockAfter: $available - $baseQty,
                        notes: "Pesanan dine-in #{$order->id}",
                        userId: $userId,
                    );

                    $batches = ProductBatch::where('product_id', $product->id)
                        ->where('warehouse_id', $warehouseId)
                        ->where('stock', '>', 0)
                        ->where(function (Builder $q) {
                            $q->whereNull('expired_at')->orWhere('expired_at', '>', now());
                        })
                        ->orderBy('expired_at')
                        ->orderBy('received_at')
                        ->lockForUpdate()
                        ->get();

                    if ($batches->isNotEmpty()) {
                        $covered = (int) $batches->sum('stock');
                        if ($covered < $baseQty) {
                            throw ValidationException::withMessages([
                                'stock' => "Stok batch {$product->title} tidak mencukupi. Tersedia: {$covered}.",
                            ]);
                        }
                        $remaining = $baseQty;
                        $firstBatchId = null;
                        foreach ($batches as $batch) {
                            if ($remaining <= 0) {
                                break;
                            }
                            $take = min((int) $batch->stock, $remaining);
                            $batch->decrement('stock', $take);
                            $remaining -= $take;
                            $firstBatchId ??= $batch->id;
                            $detail->batchAllocations()->create([
                                'product_batch_id' => $batch->id,
                                'qty' => $take,
                            ]);
                        }
                        $detail->update(['product_batch_id' => $firstBatchId]);
                    }
                }
            }

            $order->update([
                'status' => DineOrder::STATUS_ACCEPTED,
                'transaction_id' => $transaction->id,
                'cashier_id' => $userId,
            ]);
        });
    }

    public function reject(DineOrder $order, ?string $reason = null): void
    {
        $order->update([
            'status' => DineOrder::STATUS_REJECTED,
            'notes' => $order->notes
                ? "{$order->notes}\n[Penolakan: {$reason}]"
                : "[Penolakan: {$reason}]",
        ]);
    }
}
