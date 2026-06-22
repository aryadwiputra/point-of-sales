<?php

declare(strict_types=1);

namespace App\Services\Products;

use App\Models\Product;

class ProductUnitSyncService
{
    public function sync(Product $product, array $units): void
    {
        $product->units()->delete();

        foreach ($units as $unit) {
            $product->units()->create($unit);
        }
    }
}
