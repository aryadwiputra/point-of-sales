<?php

declare(strict_types=1);

namespace App\Services\SupplierReturns;

use App\Models\GoodsReceiving;
use App\Models\Product;
use App\Models\Supplier;

class SupplierReturnCreateQueryService
{
    public function execute(?int $supplierId): array
    {
        $goodsReceivings = collect();

        if ($supplierId) {
            $goodsReceivings = GoodsReceiving::with([
                'supplier:id,name',
                'items.product:id,title,sku',
                'items.purchaseOrderItem:id,unit_price',
            ])->where('supplier_id', $supplierId)
                ->whereHas('purchaseOrder', fn ($q) => $q->whereIn('status', ['ordered', 'partial_received', 'completed']))
                ->orderByDesc('received_at')
                ->get();
        }

        return [
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
            'goodsReceivings' => $goodsReceivings,
            'products' => Product::orderBy('title')->get(['id', 'title', 'sku', 'buy_price', 'stock']),
        ];
    }
}
