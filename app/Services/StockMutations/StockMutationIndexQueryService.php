<?php

declare(strict_types=1);

namespace App\Services\StockMutations;

use App\Models\Product;
use App\Models\StockMutation;

class StockMutationIndexQueryService
{
    public function execute(array $filters): array
    {
        $stockMutations = StockMutation::query()
            ->with(['product:id,title,barcode,sku', 'creator:id,name'])
            ->when($filters['product_id'], fn ($query, $productId) => $query->where('product_id', $productId))
            ->when($filters['mutation_type'], fn ($query, $mutationType) => $query->where('mutation_type', $mutationType))
            ->when($filters['date_from'], fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'], fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return [
            'stockMutations' => $stockMutations,
            'products' => Product::query()->orderBy('title')->get(['id', 'title', 'barcode', 'sku']),
            'filters' => $filters,
        ];
    }
}
