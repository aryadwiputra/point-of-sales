<?php

declare(strict_types=1);

namespace App\Services\PurchaseOrders;

use App\Models\PurchaseOrder;
use App\Services\PurchaseOrderService;

class PlacePurchaseOrderService
{
    public function __construct(
        private readonly PurchaseOrderService $purchaseOrderService
    ) {}

    public function execute(PurchaseOrder $purchaseOrder): bool
    {
        if ($purchaseOrder->status !== 'draft') {
            return false;
        }

        $this->purchaseOrderService->placeOrder($purchaseOrder);

        return true;
    }
}
