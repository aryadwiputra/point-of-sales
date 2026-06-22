<?php

declare(strict_types=1);

namespace App\Services\Products;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Services\AuditLogService;

class ProductPayloadService
{
    public function __construct(
        private readonly AuditLogService $auditLogService
    ) {}

    public function formPayload(): array
    {
        return [
            'categories' => Category::all(),
        ];
    }

    public function auditPayload(Product $product): array
    {
        $product->loadMissing('units');

        return [
            ...$this->auditLogService->only($product->toArray(), [
                'title',
                'barcode',
                'sku',
                'buy_price',
                'sell_price',
                'stock',
                'category_id',
            ]),
            'units' => $product->units
                ->map(fn (ProductUnit $unit) => $this->auditLogService->only($unit->toArray(), [
                    'label',
                    'conversion_qty',
                    'is_base_unit',
                    'buy_price',
                    'sell_price',
                    'barcode',
                ]))
                ->values()
                ->all(),
        ];
    }
}
