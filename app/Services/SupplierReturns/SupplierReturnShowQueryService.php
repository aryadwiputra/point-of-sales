<?php

declare(strict_types=1);

namespace App\Services\SupplierReturns;

use App\Models\SupplierReturn;

class SupplierReturnShowQueryService
{
    public function execute(SupplierReturn $supplierReturn): array
    {
        $supplierReturn->load([
            'supplier:id,name,phone,email,address',
            'goodsReceiving:id,document_number',
            'payable:id,total,paid,status,document_number',
            'items.product:id,title,sku',
            'items.goodsReceivingItem',
            'creator:id,name',
        ]);

        return [
            'return' => $supplierReturn,
        ];
    }
}
