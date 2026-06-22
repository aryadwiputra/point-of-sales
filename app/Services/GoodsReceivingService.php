<?php

namespace App\Services;

use App\Models\GoodsReceiving;
use App\Models\GoodsReceivingItem;
use App\Models\Payable;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GoodsReceivingService
{
    public function __construct(
        private readonly StockMutationService $stockMutationService,
        private readonly AuditLogService $auditLogService
    ) {}

    public function generateDocumentNumber(): string
    {
        $prefix = 'GR-'.now()->format('Ymd').'-';
        $last = GoodsReceiving::where('document_number', 'like', $prefix.'%')
            ->orderByDesc('document_number')
            ->value('document_number');

        $next = $last ? (int) Str::afterLast($last, '-') + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    public function receive(PurchaseOrder $order, array $items, ?string $notes, int $userId): GoodsReceiving
    {
        return DB::transaction(function () use ($order, $items, $notes, $userId) {
            $receiving = GoodsReceiving::create([
                'purchase_order_id' => $order->id,
                'supplier_id' => $order->supplier_id,
                'warehouse_id' => $order->warehouse_id,
                'document_number' => $this->generateDocumentNumber(),
                'notes' => $notes,
                'received_by' => $userId,
                'received_at' => now(),
            ]);

            foreach ($items as $item) {
                $poItem = $order->items()->findOrFail($item['purchase_order_item_id']);
                $qtyReceived = (int) $item['qty_received'];

                GoodsReceivingItem::create([
                    'goods_receiving_id' => $receiving->id,
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $poItem->product_id,
                    'qty_received' => $qtyReceived,
                    'notes' => $item['notes'] ?? null,
                ]);

                $poItem->increment('qty_received', $qtyReceived);

                $product = $poItem->product;
                // Decrement legacy stock
                $product->decrement('stock', $qtyReceived);
                // Increment warehouse pivot stock
                if ($order->warehouse_id) {
                    \App\Models\ProductWarehouse::where([
                        'product_id' => $product->id,
                        'warehouse_id' => $order->warehouse_id,
                    ])->increment('stock', $qtyReceived);
                }

                $this->stockMutationService->recordPurchaseInbound(
                    product: $product,
                    goodsReceiving: $receiving,
                    qty: $qtyReceived,
                    stockBefore: (int) $product->stock + $qtyReceived,
                    stockAfter: (int) $product->stock,
                    notes: 'Penerimaan dari PO '.$order->document_number,
                    userId: $userId,
                );
            }

            $this->updateOrderStatus($order);

            if ($receiving->supplier_id) {
                $this->createOrUpdatePayable($order, $receiving, $userId);
            }

            $this->auditLogService->log(
                event: 'goods_receiving.created',
                module: 'purchase',
                auditable: $receiving,
                description: 'Barang diterima dari PO '.$order->document_number,
                after: [
                    'document_number' => $receiving->document_number,
                    'purchase_order_id' => $order->id,
                    'total_items' => count($items),
                ],
                meta: ['goods_receiving_id' => $receiving->id],
            );

            return $receiving;
        });
    }

    private function updateOrderStatus(PurchaseOrder $order): void
    {
        $allFullyReceived = $order->items()->whereColumn('qty_received', '<', 'qty_ordered')->doesntExist();

        $status = $allFullyReceived ? 'completed' : 'partial_received';
        $updates = ['status' => $status];

        if ($status === 'completed') {
            $updates['completed_at'] = now();
        }

        $order->update($updates);
    }

    private function createOrUpdatePayable(PurchaseOrder $order, GoodsReceiving $receiving, int $userId): void
    {
        $total = $receiving->items()->sum(\DB::raw('qty_received * (SELECT unit_price FROM purchase_order_items WHERE id = goods_receiving_items.purchase_order_item_id)'));

        if ($total <= 0) {
            $total = $order->items()->sum(\DB::raw('qty_ordered * unit_price'));
        }

        $payable = Payable::updateOrCreate(
            ['purchase_order_id' => $order->id],
            [
                'supplier_id' => $order->supplier_id,
                'document_number' => $receiving->document_number,
                'total' => $total,
                'paid' => 0,
                'due_date' => now()->addDays(30),
                'status' => 'unpaid',
                'note' => 'Otomatis dari penerimaan PO '.$order->document_number,
            ]
        );

        if ($payable->wasRecentlyCreated) {
            $this->auditLogService->log(
                event: 'payable.created_from_receiving',
                module: 'payable',
                auditable: $payable,
                description: 'Hutang otomatis dari penerimaan PO '.$order->document_number,
                after: [
                    'payable_id' => $payable->id,
                    'supplier_id' => $payable->supplier_id,
                    'total' => $payable->total,
                    'document_number' => $payable->document_number,
                    'purchase_order_id' => $order->id,
                ],
                meta: ['goods_receiving_id' => $receiving->id],
            );
        }
    }
}
