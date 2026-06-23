<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductBatch;
use Illuminate\Support\Collection;

class BatchService
{
    public function getAvailableBatches(int $productId, int $warehouseId, int $qtyNeeded): Collection
    {
        return ProductBatch::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('stock', '>', 0)
            ->where(function ($q) { $q->whereNull('expired_at')->orWhere('expired_at', '>', now()); })
            ->orderBy('expired_at')
            ->orderBy('received_at')
            ->get();
    }

    public function allocate(Product $product, int $warehouseId, int $qty): array
    {
        $batches = $this->getAvailableBatches($product->id, $warehouseId, $qty);
        $allocations = [];
        $remaining = $qty;

        foreach ($batches as $batch) {
            if ($remaining <= 0) break;
            $take = min($batch->stock, $remaining);
            $allocations[] = [
                'batch_id' => $batch->id,
                'batch_number' => $batch->batch_number,
                'expired_at' => $batch->expired_at,
                'qty' => $take,
            ];
            $remaining -= $take;
        }

        return $allocations;
    }

    public function getExpiringSoon(int $days = 30): Collection
    {
        return ProductBatch::with(['product:id,title', 'warehouse:id,name'])
            ->expiringSoon($days)
            ->orderBy('expired_at')
            ->limit(20)
            ->get();
    }

    public function getExpired(): Collection
    {
        return ProductBatch::with(['product:id,title', 'warehouse:id,name'])
            ->expired()
            ->orderBy('expired_at')
            ->get();
    }
}
