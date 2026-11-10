<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Apps\PurchaseOrderController as AppsPurchaseOrderController;
use App\Http\Traits\ApiResponder;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseOrderController extends AppsPurchaseOrderController
{
    use ApiResponder;

    /**
     * GET /api/v1/purchase-orders
     * Paginated purchase order list with filters.
     */
    public function listOrders(Request $request): JsonResponse
    {
        $filters = [
            'status' => $request->input('status'),
            'supplier' => $request->input('supplier'),
            'search' => $request->input('search'),
        ];

        $warehouseIds = $this->outletAccessService->warehousesFor($request->user())->pluck('id');
        $query = PurchaseOrder::with([
            'supplier:id,name',
            'items',
            'creator:id,name',
        ])->where(function ($query) use ($warehouseIds) {
            $query->whereIn('warehouse_id', $warehouseIds)->orWhereNull('warehouse_id');
        })->withCount('items as items_count')
            ->orderByDesc('created_at');

        $query->when($filters['status'], fn ($q, $s) => $q->where('status', $s))
            ->when($filters['supplier'], fn ($q, $s) => $q->where('supplier_id', $s))
            ->when($filters['search'], fn ($q, $s) => $q->where('document_number', 'like', "%{$s}%"));

        $orders = $query->paginate($this->perPage())->withQueryString();

        return $this->paginated($orders);
    }

    /**
     * GET /api/v1/purchase-orders/create
     * Reference data (suppliers, products, warehouses) for creating an order.
     */
    public function createData(Request $request): JsonResponse
    {
        return $this->ok([
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
            'products' => Product::orderBy('title')->get(['id', 'title', 'sku', 'buy_price', 'stock']),
            'warehouses' => $this->outletAccessService->warehousesFor($request->user()),
        ]);
    }

    /**
     * POST /api/v1/purchase-orders
     * Create a purchase order.
     */
    public function storeOrder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'warehouse_id' => ['nullable', 'exists:warehouses,id'],
            'document_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.qty_ordered' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        $order = $this->purchaseOrderService->createOrder($data, $data['items'], $request->user()->id);

        return $this->created($this->orderDetail($order), 'Purchase order berhasil dibuat.');
    }

    /**
     * GET /api/v1/purchase-orders/{purchaseOrder}
     * Purchase order detail.
     */
    public function showOrder(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->ensureOrderAccess($request, $purchaseOrder);

        return $this->ok($this->orderDetail($purchaseOrder));
    }

    /**
     * POST /api/v1/purchase-orders/{purchaseOrder}/place
     * Place a draft purchase order.
     */
    public function placeOrderApi(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->ensureOrderAccess($request, $purchaseOrder);
        if ($purchaseOrder->status !== 'draft') {
            return $this->error('Hanya PO dengan status draft yang bisa dipesan.', 422);
        }

        $this->purchaseOrderService->placeOrder($purchaseOrder);

        return $this->ok($this->orderDetail($purchaseOrder->fresh()), 'Purchase order berhasil dipesan.');
    }

    /**
     * POST /api/v1/purchase-orders/{purchaseOrder}/cancel
     * Cancel a purchase order.
     */
    public function cancelOrderApi(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->ensureOrderAccess($request, $purchaseOrder);
        if (! in_array($purchaseOrder->status, ['draft', 'ordered', 'partial_received'])) {
            return $this->error('PO tidak dapat dibatalkan.', 422);
        }

        $this->purchaseOrderService->cancelOrder($purchaseOrder);

        return $this->ok(null, 'Purchase order dibatalkan.');
    }

    protected function orderDetail(PurchaseOrder $purchaseOrder): array
    {
        $purchaseOrder->load([
            'supplier:id,name,phone,email,address',
            'warehouse:id,code,name',
            'items.product:id,title,sku,image',
            'goodsReceivings' => function ($q) {
                $q->with('items.product:id,title,sku')->orderByDesc('received_at');
            },
            'creator:id,name',
            'payable:id,purchase_order_id,total,paid,status,document_number',
        ]);

        return $purchaseOrder->toArray();
    }
}
