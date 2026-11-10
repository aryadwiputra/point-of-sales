<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Apps\GoodsReceivingController as AppsGoodsReceivingController;
use App\Http\Traits\ApiResponder;
use App\Models\GoodsReceiving;
use App\Models\PurchaseOrder;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoodsReceivingController extends AppsGoodsReceivingController
{
    use ApiResponder;

    /**
     * GET /api/v1/goods-receivings
     * Paginated goods receiving list with filters.
     */
    public function listReceivings(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->input('search'),
            'purchase_order_id' => $request->input('purchase_order_id'),
        ];

        $warehouseIds = $this->outletAccessService->warehousesFor($request->user())->pluck('id');
        $query = GoodsReceiving::with([
            'purchaseOrder:id,document_number,status',
            'supplier:id,name',
            'receiver:id,name',
        ])->whereIn('warehouse_id', $warehouseIds)->orderByDesc('received_at');

        $query->when($filters['search'], fn ($q, $s) => $q->where('document_number', 'like', "%{$s}%"))
            ->when($filters['purchase_order_id'], fn ($q, $id) => $q->where('purchase_order_id', $id));

        $receivings = $query->paginate($this->perPage())->withQueryString();

        return $this->paginated($receivings);
    }

    /**
     * GET /api/v1/goods-receivings/create
     * List of receivable POs (ordered/partial_received) for creating a receiving.
     */
    public function createData(Request $request): JsonResponse
    {
        $purchaseOrderId = $request->input('purchase_order_id');

        $orders = PurchaseOrder::with([
            'supplier:id,name',
            'items.product:id,title,sku',
        ])->whereIn('status', ['ordered', 'partial_received'])
            ->where(function ($query) use ($request) {
                $ids = $this->outletAccessService->warehousesFor($request->user())->pluck('id');
                $query->whereIn('warehouse_id', $ids)->orWhereNull('warehouse_id');
            })
            ->orderByDesc('created_at')
            ->get();

        if ($purchaseOrderId) {
            $orders = $orders->where('id', $purchaseOrderId);
        }

        return $this->ok(['orders' => $orders]);
    }

    /**
     * POST /api/v1/goods-receivings
     * Record goods receiving for a purchase order.
     */
    public function storeReceiving(Request $request): JsonResponse
    {
        $data = $request->validate([
            'purchase_order_id' => ['required', 'exists:purchase_orders,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['required', 'exists:purchase_order_items,id'],
            'items.*.qty_received' => ['required', 'integer', 'min:1'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
            'items.*.batch_number' => ['nullable', 'string', 'max:255'],
            'items.*.expired_at' => ['nullable', 'date'],
        ]);

        $order = PurchaseOrder::with('items')->findOrFail($data['purchase_order_id']);

        if ($order->warehouse_id) {
            $warehouse = Warehouse::findOrFail($order->warehouse_id);
            abort_unless($this->outletAccessService->canUseWarehouse($request->user(), $warehouse), 403);
        }

        if (! in_array($order->status, ['ordered', 'partial_received'])) {
            return $this->error('Hanya PO berstatus ordered/partial yang dapat diterima.', 422);
        }

        $seen = [];
        foreach ($data['items'] as $item) {
            if (in_array($item['purchase_order_item_id'], $seen)) {
                return $this->error('Item PO duplikat dalam satu penerimaan.', 422);
            }
            $seen[] = $item['purchase_order_item_id'];

            $poItem = $order->items->firstWhere('id', $item['purchase_order_item_id']);
            if (! $poItem) {
                return $this->error('Item tidak ditemukan di PO.', 422);
            }
            $outstanding = $poItem->qty_ordered - $poItem->qty_received;
            if ($item['qty_received'] > $outstanding) {
                return $this->error("Qty diterima melebihi sisa item {$poItem->product_id}.", 422);
            }
        }

        $receiving = $this->goodsReceivingService->receive(
            order: $order,
            items: $data['items'],
            notes: $data['notes'] ?? null,
            userId: $request->user()->id,
        );

        return $this->created($this->receivingDetail($receiving), 'Penerimaan barang berhasil dicatat.');
    }

    /**
     * GET /api/v1/goods-receivings/{goodsReceiving}
     * Receiving detail.
     */
    public function showReceiving(GoodsReceiving $goodsReceiving): JsonResponse
    {
        $warehouse = $goodsReceiving->warehouse_id ? $goodsReceiving->warehouse : null;
        abort_unless($this->outletAccessService->canUseWarehouse(request()->user(), $warehouse), 404);

        return $this->ok($this->receivingDetail($goodsReceiving));
    }

    protected function receivingDetail(GoodsReceiving $goodsReceiving): array
    {
        $goodsReceiving->load([
            'purchaseOrder:id,document_number,status',
            'supplier:id,name',
            'items.product:id,title,sku',
            'items.purchaseOrderItem:id,unit_price',
            'receiver:id,name',
        ]);

        return $goodsReceiving->toArray();
    }
}
