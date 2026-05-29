<?php

declare(strict_types=1);

namespace App\Services\StockOpnames;

use App\Models\Product;
use App\Models\StockOpname;

class StockOpnameShowQueryService
{
    public function execute(StockOpname $stockOpname, array $productFilters): array
    {
        $stockOpname->load([
            'creator:id,name',
            'finalizer:id,name',
            'items.product.category:id,name',
        ]);

        $selectedProductIds = $stockOpname->items->pluck('product_id');

        $availableProducts = blank($productFilters['search'])
            ? collect()
            : Product::query()
                ->with('category:id,name')
                ->where(function ($builder) use ($productFilters) {
                    $builder
                        ->where('title', 'like', '%'.$productFilters['search'].'%')
                        ->orWhere('barcode', 'like', '%'.$productFilters['search'].'%')
                        ->orWhere('sku', 'like', '%'.$productFilters['search'].'%');
                })
                ->whereNotIn('id', $selectedProductIds)
                ->orderBy('title')
                ->limit(20)
                ->get();

        return [
            'stockOpname' => $stockOpname,
            'availableProducts' => $availableProducts,
            'productFilters' => $productFilters,
        ];
    }
}
