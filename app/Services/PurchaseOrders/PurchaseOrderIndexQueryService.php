<?php

declare(strict_types=1);

namespace App\Services\PurchaseOrders;

use App\Models\PurchaseOrder;
use App\Models\Supplier;

class PurchaseOrderIndexQueryService
{
    public function execute(array $filters): array
    {
        $query = PurchaseOrder::with([
            'supplier:id,name',
            'items',
            'creator:id,name',
        ])->withCount('items as items_count')
            ->orderByDesc('created_at');

        $query->when($filters['status'], fn ($q, $status) => $q->where('status', $status))
            ->when($filters['supplier'], fn ($q, $supplier) => $q->where('supplier_id', $supplier))
            ->when($filters['search'], fn ($q, $search) => $q->where('document_number', 'like', "%{$search}%"));

        return [
            'orders' => $query->paginate(10)->withQueryString(),
            'filters' => $filters,
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
        ];
    }
}
