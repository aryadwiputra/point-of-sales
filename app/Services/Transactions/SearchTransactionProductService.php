<?php

declare(strict_types=1);

namespace App\Services\Transactions;

use App\Models\Product;
use App\Models\ProductUnit;

class SearchTransactionProductService
{
    public function execute(?string $barcode): ?array
    {
        $barcode = (string) $barcode;

        $unit = ProductUnit::with('product.units')
            ->where('barcode', $barcode)
            ->first();

        if ($unit?->product) {
            return [
                ...$unit->product->toArray(),
                'selected_unit' => $unit,
            ];
        }

        $product = Product::with('units')->where('barcode', $barcode)->first();

        return $product?->toArray();
    }
}
