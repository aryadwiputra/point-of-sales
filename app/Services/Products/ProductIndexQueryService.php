<?php

declare(strict_types=1);

namespace App\Services\Products;

use App\Models\Product;

class ProductIndexQueryService
{
    public function execute(?string $search): array
    {
        $products = Product::when($search, function ($products) use ($search) {
            $products
                ->where('title', 'like', '%'.$search.'%')
                ->orWhere('barcode', 'like', '%'.$search.'%')
                ->orWhere('sku', 'like', '%'.$search.'%')
                ->orWhereHas('units', function ($units) use ($search) {
                    $units->where('label', 'like', '%'.$search.'%')
                        ->orWhere('barcode', 'like', '%'.$search.'%');
                });
        })->with(['category', 'units'])->latest()->paginate(5);

        return [
            'products' => $products,
        ];
    }
}
