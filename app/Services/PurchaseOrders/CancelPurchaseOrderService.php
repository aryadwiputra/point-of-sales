<?php

declare(strict_types=1);

namespace App\Services\PurchaseOrders;

use App\Models\PurchaseOrder;
use App\Services\PurchaseOrderService;

class CancelPurchaseOrderService
{
    public function __construct(
        private readonly PurchaseOrderService $purchaseOrderService
    ) {}

    public function execute(PurchaseOrder $purchaseOrder): bool
    {
        if (! in_array($purchaseOrder->status, ['draft', 'ordered', 'partial_received'], true)) {
            return false;
        }

        $this->purchaseOrderService->cancelOrder($purchaseOrder);

        return true;
    }
}
