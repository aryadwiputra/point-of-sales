<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\ProductNotificationRead;

class MarkLowStockNotificationReadService
{
    public function execute(int $userId, int $productId): void
    {
        ProductNotificationRead::updateOrCreate(
            [
                'user_id' => $userId,
                'product_id' => $productId,
            ],
            []
        );
    }
}
