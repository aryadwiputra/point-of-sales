<?php

declare(strict_types=1);

namespace App\Services\SupplierReturns;

use App\Models\Supplier;
use App\Models\SupplierReturn;

class SupplierReturnIndexQueryService
{
    public function execute(array $filters): array
    {
        $query = SupplierReturn::with([
            'supplier:id,name',
            'creator:id,name',
        ])->withCount('items as items_count')
            ->orderByDesc('created_at');

        $query->when($filters['status'], fn ($q, $status) => $q->where('status', $status))
            ->when($filters['supplier'], fn ($q, $supplier) => $q->where('supplier_id', $supplier))
            ->when($filters['search'], fn ($q, $search) => $q->where('document_number', 'like', "%{$search}%"));

        return [
            'returns' => $query->paginate(10)->withQueryString(),
            'filters' => $filters,
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
        ];
    }
}
