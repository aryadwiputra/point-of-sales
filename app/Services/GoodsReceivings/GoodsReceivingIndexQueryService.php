<?php

declare(strict_types=1);

namespace App\Services\GoodsReceivings;

use App\Models\GoodsReceiving;

class GoodsReceivingIndexQueryService
{
    public function execute(array $filters): array
    {
        $query = GoodsReceiving::with([
            'purchaseOrder:id,document_number,status',
            'supplier:id,name',
            'receiver:id,name',
        ])->orderByDesc('received_at');

        $query->when($filters['search'], fn ($q, $search) => $q->where('document_number', 'like', "%{$search}%"))
            ->when($filters['purchase_order_id'], fn ($q, $purchaseOrderId) => $q->where('purchase_order_id', $purchaseOrderId));

        return [
            'receivings' => $query->paginate(10)->withQueryString(),
            'filters' => $filters,
        ];
    }
}
