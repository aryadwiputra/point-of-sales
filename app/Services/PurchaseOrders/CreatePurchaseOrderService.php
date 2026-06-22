<?php

declare(strict_types=1);

namespace App\Services\PurchaseOrders;

use App\Models\PurchaseOrder;
use App\Services\PurchaseOrderService;

class CreatePurchaseOrderService
{
    public function __construct(
        private readonly PurchaseOrderService $purchaseOrderService
    ) {}

    public function execute(array $data, int $userId): PurchaseOrder
    {
        return $this->purchaseOrderService->createOrder($data, $data['items'], $userId);
    }
}
