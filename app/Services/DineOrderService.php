<?php

namespace App\Services;

use App\Models\CashierShift;
use App\Models\DineOrder;
use App\Models\ProductWarehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DineOrderService
{
    public function __construct(
        private PricingService $pricingService,
    ) {}

    public function accept(DineOrder $order): void
    {
        $cashierId = $order->cashier_id ?? auth()->id();
        $shift = CashierShift::where('user_id', $cashierId)->open()->first();

        // ponytail: without an open shift there is no warehouse context to take stock from — refuse instead of accepting an order whose stock is never decremented
        if (! $shift) {
            throw ValidationException::withMessages([
                'shift' => 'Tidak ada shift kasir aktif. Buka shift terlebih dahulu sebelum menerima pesanan.',
            ]);
        }

        $warehouseId = $shift->warehouse_id;

        DB::transaction(function () use ($order, $warehouseId) {
            // ponytail: lock the order row so accept cannot race with a second accept/reject
            $order = DineOrder::with('items.product.components')->whereKey($order->id)->lockForUpdate()->firstOrFail();

            foreach ($order->items as $item) {
                $product = $item->product;

                if ($product->is_composite) {
                    foreach ($product->components as $component) {
                        $componentQty = $item->qty * (int) round($component->pivot->qty);

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
                    }
                } else {
                    $pw = ProductWarehouse::where('product_id', $product->id)
                        ->where('warehouse_id', $warehouseId)
                        ->lockForUpdate()
                        ->first();

                    $available = $pw ? (int) $pw->stock : (int) $product->stock;

                    if ($available < $item->qty) {
                        throw ValidationException::withMessages([
                            'stock' => "Stok {$product->title} tidak mencukupi (tersedia: {$available}).",
                        ]);
                    }

                    $pw?->decrement('stock', $item->qty);
                    $product->decrement('stock', $item->qty);
                }
            }

            $order->update(['status' => DineOrder::STATUS_ACCEPTED]);
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
