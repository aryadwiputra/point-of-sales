<?php

declare(strict_types=1);

namespace App\Services\PurchaseOrders;

use App\Models\Product;
use App\Models\Supplier;

class PurchaseOrderCreateQueryService
{
    public function execute(): array
    {
        return [
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
            'products' => Product::orderBy('title')->get(['id', 'title', 'sku', 'buy_price', 'stock']),
        ];
    }
}
