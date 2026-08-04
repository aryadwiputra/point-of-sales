<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Unit;

class UnitConversionService
{
    public function toBaseUnit(Product $product, int $unitId, int $qty): int
    {
        $pu = $product->units()->where('unit_id', $unitId)->first();
        $factor = $pu?->pivot->conversion_factor ?? 1;

        return (int) round($qty * $factor);
    }

    public function fromBaseUnit(Product $product, int $unitId, int $baseQty): float
    {
        $pu = $product->units()->where('unit_id', $unitId)->first();
        $factor = $pu?->pivot->conversion_factor ?? 1;

        return $factor > 0 ? $baseQty / $factor : $baseQty;
    }

    public function getPrice(Product $product, int $unitId, string $type = 'sell_price'): int
    {
        $pu = $product->units()->where('unit_id', $unitId)->first();

        return (int) ($pu?->pivot->{$type} ?? $product->{$type});
    }

    public function getBuyPrice(Product $product, int $unitId): int
    {
        return $this->getPrice($product, $unitId, 'buy_price');
    }

    public function getSellPrice(Product $product, int $unitId): int
    {
        return $this->getPrice($product, $unitId, 'sell_price');
    }

    public function getUnitLabel(Product $product, ?int $unitId): string
    {
        if (! $unitId) {
            return 'pcs';
        }
        $unit = Unit::find($unitId);

        return $unit?->symbol ?? 'pcs';
    }
}
