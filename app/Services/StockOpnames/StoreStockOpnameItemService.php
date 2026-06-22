<?php

declare(strict_types=1);

namespace App\Services\StockOpnames;

use App\Models\Product;
use App\Models\StockOpname;
use Illuminate\Validation\ValidationException;

class StoreStockOpnameItemService
{
    public function __construct(
        private readonly StockOpnameGuardService $guard
    ) {}

    public function execute(StockOpname $stockOpname, int $productId): void
    {
        $this->guard->ensureDraft($stockOpname);

        $product = Product::findOrFail($productId);

        if ($stockOpname->items()->where('product_id', $product->id)->exists()) {
            throw ValidationException::withMessages([
                'product_id' => 'Produk sudah ada di sesi stock opname ini.',
            ]);
        }

        $stockOpname->items()->create([
            'product_id' => $product->id,
            'system_stock' => $product->stock,
        ]);
    }
}
