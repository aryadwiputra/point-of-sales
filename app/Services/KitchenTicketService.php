<?php

namespace App\Services;

use App\Models\KitchenStation;
use App\Models\KitchenStationCategory;
use App\Models\Product;
use App\Models\ProductKitchenStationOverride;

class KitchenTicketService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly OutletAccessService $outletAccessService,
    ) {}

    public function routeFor(Product $product, ?int $outletId): ?KitchenStation
    {
        $override = ProductKitchenStationOverride::query()
            ->with('station')
            ->where('product_id', $product->id)
            ->where('outlet_id', $outletId)
            ->whereHas('station', fn ($query) => $query->where('is_active', true))
            ->get()
            ->sortBy(fn (ProductKitchenStationOverride $row) => sprintf('%05d-%05d', $row->station->sort_order, $row->station->id))
            ->first();

        if ($override) {
            return $override->station;
        }

        if (! $product->category_id) {
            return null;
        }

        $mapping = KitchenStationCategory::query()
            ->with('station')
            ->where('category_id', $product->category_id)
            ->where('outlet_id', $outletId)
            ->whereHas('station', fn ($query) => $query->where('is_active', true))
            ->get()
            ->sortBy(fn (KitchenStationCategory $row) => sprintf('%05d-%05d', $row->station->sort_order, $row->station->id))
            ->first();

        return $mapping?->station;
    }
}
