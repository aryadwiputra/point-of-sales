<?php

namespace App\Services;

use App\Models\CashierShift;
use App\Models\DineOrder;
use App\Models\ProductWarehouse;

class DineOrderService
{
    public function __construct(
        private PricingService $pricingService,
    ) {}

    public function accept(DineOrder $order): void
    {
        $order->update(['status' => DineOrder::STATUS_ACCEPTED]);

        $cashierId = $order->cashier_id ?? auth()->id();
        $shift = CashierShift::where('user_id', $cashierId)->open()->first();

        if (! $shift) {
            return;
        }

        $warehouseId = $shift->warehouse_id;

        foreach ($order->items as $item) {
            $product = $item->product;

            if ($product->is_composite) {
                foreach ($product->components as $component) {
                    $componentProduct = $component->componentProduct;
                    $componentProduct->decrement('stock', $item->qty * $component->qty);
                    ProductWarehouse::where('product_id', $componentProduct->id)
                        ->where('warehouse_id', $warehouseId)
                        ->decrement('stock', $item->qty * $component->qty);
                }
            } else {
                $product->decrement('stock', $item->qty);
                ProductWarehouse::where('product_id', $product->id)
                    ->where('warehouse_id', $warehouseId)
                    ->decrement('stock', $item->qty);
            }
        }
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
