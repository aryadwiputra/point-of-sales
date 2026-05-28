<?php

declare(strict_types=1);

namespace App\Services\GoodsReceivings;

use App\Models\PurchaseOrder;

class GoodsReceivingCreateQueryService
{
    public function execute(?int $purchaseOrderId): array
    {
        $orders = PurchaseOrder::with([
            'supplier:id,name',
            'items.product:id,title,sku',
        ])->whereIn('status', ['ordered', 'partial_received'])
            ->when($purchaseOrderId, fn ($query, $id) => $query->whereKey($id))
            ->orderByDesc('created_at')
            ->get();

        return [
            'orders' => $orders,
        ];
    }
}
