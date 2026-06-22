<?php

namespace App\Services;

use App\Models\Product;
use App\Models\PurchaseOrder;
use Illuminate\Support\Collection;

class ReorderService
{
    public function getLowStockProducts(?int $warehouseId = null): Collection
    {
        $query = Product::where('min_stock', '>', 0);

        if ($warehouseId) {
            $query->whereHas('warehouses', function ($q) use ($warehouseId) {
                $q->where('product_warehouse.warehouse_id', $warehouseId)
                  ->whereColumn('product_warehouse.stock', '<=', 'products.min_stock');
            });
        } else {
            $query->whereColumn('stock', '<=', 'min_stock');
        }

        return $query->orderBy('stock')->limit(20)->get();
    }

    public function createDraftPurchaseOrder(Collection $products, int $userId): ?PurchaseOrder
    {
        $items = $products->filter(fn ($p) => $p->suggestedOrderQty() > 0)
            ->map(fn ($p) => [
                'product_id' => $p->id,
                'qty_ordered' => $p->suggestedOrderQty(),
                'unit_price' => $p->buy_price,
            ])->values()->toArray();

        if (empty($items)) return null;

        return app(PurchaseOrderService::class)->createOrder(
            ['notes' => 'Auto-generated from restock suggestion'],
            $items,
            $userId
        );
    }
}
