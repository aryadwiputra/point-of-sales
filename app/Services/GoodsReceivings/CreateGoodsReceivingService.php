<?php

declare(strict_types=1);

namespace App\Services\GoodsReceivings;

use App\Models\PurchaseOrder;
use App\Services\GoodsReceivingService;

class CreateGoodsReceivingService
{
    public function __construct(
        private readonly GoodsReceivingService $goodsReceivingService
    ) {}

    public function execute(array $data, int $userId): array
    {
        $order = PurchaseOrder::with('items')->findOrFail($data['purchase_order_id']);

        foreach ($data['items'] as $item) {
            $poItem = $order->items->firstWhere('id', $item['purchase_order_item_id']);

            if (! $poItem) {
                return [
                    'receiving' => null,
                    'error' => 'Item tidak ditemukan di PO.',
                ];
            }

            $outstanding = $poItem->qty_ordered - $poItem->qty_received;

            if ($item['qty_received'] > $outstanding) {
                return [
                    'receiving' => null,
                    'error' => "Qty diterima melebihi sisa item {$poItem->product_id}.",
                ];
            }
        }

        return [
            'receiving' => $this->goodsReceivingService->receive(
                order: $order,
                items: $data['items'],
                notes: $data['notes'] ?? null,
                userId: $userId,
            ),
            'error' => null,
        ];
    }
}
