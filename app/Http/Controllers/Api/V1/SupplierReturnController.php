<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Apps\SupplierReturnController as AppsSupplierReturnController;
use App\Http\Traits\ApiResponder;
use App\Models\GoodsReceiving;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierReturn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierReturnController extends AppsSupplierReturnController
{
    use ApiResponder;

    /**
     * GET /api/v1/supplier-returns
     * Paginated supplier return list with filters.
     */
    public function listReturns(Request $request): JsonResponse
    {
        $filters = [
            'status' => $request->input('status'),
            'supplier' => $request->input('supplier'),
            'search' => $request->input('search'),
        ];

        $warehouseIds = $this->outletAccessService->warehousesFor($request->user())->pluck('id');
        $query = SupplierReturn::with([
            'supplier:id,name',
            'creator:id,name',
        ])->withCount('items as items_count')
            ->whereIn('warehouse_id', $warehouseIds)
            ->orderByDesc('created_at');

        $query->when($filters['status'], fn ($q, $s) => $q->where('status', $s))
            ->when($filters['supplier'], fn ($q, $s) => $q->where('supplier_id', $s))
            ->when($filters['search'], fn ($q, $s) => $q->where('document_number', 'like', "%{$s}%"));

        $returns = $query->paginate($this->perPage())->withQueryString();

        return $this->paginated($returns);
    }

    /**
     * GET /api/v1/supplier-returns/create
     * Reference data (suppliers, goods receivings, products) for creating a return.
     */
    public function createData(Request $request): JsonResponse
    {
        $suppliers = Supplier::orderBy('name')->get(['id', 'name']);

        $goodsReceivings = collect();
        if ($request->input('supplier_id')) {
            $goodsReceivings = GoodsReceiving::with([
                'supplier:id,name',
                'items.product:id,title,sku',
                'items.purchaseOrderItem:id,unit_price',
            ])->where('supplier_id', $request->input('supplier_id'))
                ->whereIn('warehouse_id', $this->outletAccessService->warehousesFor($request->user())->pluck('id'))
                ->whereHas('purchaseOrder', fn ($q) => $q->whereIn('status', ['ordered', 'partial_received', 'completed']))
                ->orderByDesc('received_at')
                ->get();
        }

        $products = Product::orderBy('title')->get(['id', 'title', 'sku', 'buy_price', 'stock']);

        return $this->ok([
            'suppliers' => $suppliers,
            'goods_receivings' => $goodsReceivings,
            'products' => $products,
        ]);
    }

    /**
     * POST /api/v1/supplier-returns
     * Create a supplier return.
     */
    public function storeReturn(Request $request): JsonResponse
    {
        $data = $request->validate([
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'goods_receiving_id' => ['nullable', 'exists:goods_receivings,id'],
            'payable_id' => ['nullable', 'exists:payables,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.qty_returned' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.reason' => ['nullable', 'string', 'max:100'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ]);

        $return = $this->supplierReturnService->createReturn(
            $data,
            $data['items'],
            $request->user()->id,
        );

        return $this->created($this->returnDetail($return), 'Retur supplier berhasil dibuat.');
    }

    /**
     * GET /api/v1/supplier-returns/{supplierReturn}
     * Supplier return detail.
     */
    public function showReturn(SupplierReturn $supplierReturn): JsonResponse
    {
        $this->ensureAccess(request(), $supplierReturn);

        return $this->ok($this->returnDetail($supplierReturn));
    }

    /**
     * POST /api/v1/supplier-returns/{supplierReturn}/complete
     * Complete a draft supplier return.
     */
    public function completeReturnApi(Request $request, SupplierReturn $supplierReturn): JsonResponse
    {
        $this->ensureAccess($request, $supplierReturn);

        if ($supplierReturn->status !== 'draft') {
            return $this->error('Hanya retur dengan status draft yang bisa diselesaikan.', 422);
        }

        $this->supplierReturnService->complete($supplierReturn, $request->user()->id);

        return $this->ok($this->returnDetail($supplierReturn->fresh()), 'Retur supplier berhasil diselesaikan.');
    }

    /**
     * POST /api/v1/supplier-returns/{supplierReturn}/cancel
     * Cancel a draft supplier return.
     */
    public function cancelReturnApi(Request $request, SupplierReturn $supplierReturn): JsonResponse
    {
        $this->ensureAccess($request, $supplierReturn);

        if (! in_array($supplierReturn->status, ['draft'])) {
            return $this->error('Retur tidak dapat dibatalkan.', 422);
        }

        $this->supplierReturnService->cancel($supplierReturn);

        return $this->ok(null, 'Retur supplier dibatalkan.');
    }

    protected function returnDetail(SupplierReturn $supplierReturn): array
    {
        $supplierReturn->load([
            'supplier:id,name,phone,email,address',
            'goodsReceiving:id,document_number',
            'payable:id,total,paid,status,document_number',
            'items.product:id,title,sku',
            'items.goodsReceivingItem',
            'creator:id,name',
        ]);

        return $supplierReturn->toArray();
    }
}
