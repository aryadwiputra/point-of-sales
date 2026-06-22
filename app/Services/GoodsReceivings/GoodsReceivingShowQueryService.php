<?php

declare(strict_types=1);

namespace App\Services\GoodsReceivings;

use App\Models\GoodsReceiving;

class GoodsReceivingShowQueryService
{
    public function execute(GoodsReceiving $goodsReceiving): array
    {
        $goodsReceiving->load([
            'purchaseOrder:id,document_number,status',
            'supplier:id,name',
            'items.product:id,title,sku',
            'items.purchaseOrderItem:id,unit_price',
            'receiver:id,name',
        ]);

        return [
            'receiving' => $goodsReceiving,
        ];
    }
}
