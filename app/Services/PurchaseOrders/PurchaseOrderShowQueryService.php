<?php

declare(strict_types=1);

namespace App\Services\PurchaseOrders;

use App\Models\PurchaseOrder;

class PurchaseOrderShowQueryService
{
    public function execute(PurchaseOrder $purchaseOrder): array
    {
        $purchaseOrder->load([
            'supplier:id,name,phone,email,address',
            'items.product:id,title,sku,image',
            'goodsReceivings' => function ($q) {
                $q->with('items.product:id,title,sku')->orderByDesc('received_at');
            },
            'creator:id,name',
            'payable:id,purchase_order_id,total,paid,status,document_number',
        ]);

        return [
            'order' => $purchaseOrder,
        ];
    }
}
