<?php

declare(strict_types=1);

namespace App\Services\Transactions;

use App\Models\Product;
use App\Models\ProductUnit;

class ProductUnitResolverService
{
    public function resolve(Product $product, ?int $productUnitId): ProductUnit
    {
        if ($productUnitId) {
            $unit = $product->units->firstWhere('id', $productUnitId);

            if ($unit) {
                return $unit;
            }
        }

        $unit = $product->units->firstWhere('is_base_unit', true) ?? $product->units->first();

        if ($unit) {
            return $unit;
        }

        return ProductUnit::create([
            'product_id' => $product->id,
            'label' => 'pcs',
            'conversion_qty' => 1,
            'is_base_unit' => true,
            'sell_price' => $product->sell_price,
            'buy_price' => $product->buy_price,
            'barcode' => $product->barcode,
        ]);
    }
}
