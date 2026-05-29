<?php

declare(strict_types=1);

namespace App\Services\StockOpnames;

use App\Models\StockOpname;

class StockOpnameIndexQueryService
{
    public function execute(array $filters): array
    {
        $stockOpnames = StockOpname::query()
            ->with(['creator:id,name', 'finalizer:id,name'])
            ->when($filters['search'], function ($query, $search) {
                $query->where(function ($builder) use ($search) {
                    $builder
                        ->where('code', 'like', '%'.$search.'%')
                        ->orWhere('notes', 'like', '%'.$search.'%');
                });
            })
            ->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
            ->when($filters['date_from'], fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'], fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->withCount('items')
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return [
            'stockOpnames' => $stockOpnames,
            'filters' => $filters,
        ];
    }
}
