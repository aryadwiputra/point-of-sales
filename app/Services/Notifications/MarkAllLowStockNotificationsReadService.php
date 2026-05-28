<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\Product;
use App\Models\ProductNotificationRead;

class MarkAllLowStockNotificationsReadService
{
    public function execute(int $userId): bool
    {
        $productIds = Product::query()
            ->where('stock', '<=', 0)
            ->pluck('id')
            ->all();

        if ($productIds === []) {
            return false;
        }

        $payload = collect($productIds)->map(fn ($productId) => [
            'user_id' => $userId,
            'product_id' => $productId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ProductNotificationRead::upsert(
            $payload->toArray(),
            ['user_id', 'product_id'],
            ['updated_at']
        );

        return true;
    }
}
