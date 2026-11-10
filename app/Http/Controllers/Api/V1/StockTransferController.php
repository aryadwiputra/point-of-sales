<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Apps\StockTransferController as AppsStockTransferController;
use App\Http\Traits\ApiResponder;
use App\Models\Product;
use App\Models\StockTransfer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockTransferController extends AppsStockTransferController
{
    use ApiResponder;

    /**
     * GET /api/v1/stock-transfers
     * Paginated transfer list.
     */
    public function listTransfers(Request $request): JsonResponse
    {
        $warehouses = $this->outletAccessService->warehousesFor($request->user());
        $warehouseIds = $warehouses->pluck('id');

        $transfers = StockTransfer::with([
            'sourceWarehouse:id,code,name',
            'destinationWarehouse:id,code,name',
            'creator:id,name',
        ])->where(function ($query) use ($warehouseIds) {
            $query->whereIn('source_warehouse_id', $warehouseIds)
                ->orWhereIn('destination_warehouse_id', $warehouseIds);
        })->withCount('items')
            ->latest()
            ->paginate($this->perPage())
            ->withQueryString();

        return $this->paginated($transfers);
    }

    /**
     * GET /api/v1/stock-transfers/create
     * Reference data for creating a transfer.
     */
    public function createData(Request $request): JsonResponse
    {
        $warehouses = $this->outletAccessService->warehousesFor($request->user());
        $products = Product::orderBy('title')->get(['id', 'title', 'sku', 'stock']);

        return $this->ok([
            'warehouses' => $warehouses,
            'products' => $products,
        ]);
    }

    /**
     * POST /api/v1/stock-transfers
     * Create a draft stock transfer.
     */
    public function storeTransfer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'source_warehouse_id' => ['required', 'exists:warehouses,id'],
            'destination_warehouse_id' => ['required', 'exists:warehouses,id', 'different:source_warehouse_id'],
            'document_number' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
        ]);

        $transfer = $this->stockTransferService->createDraft($data, $data['items'], $request->user()->id);

        return $this->created($this->transferDetail($transfer), 'Transfer stok berhasil dibuat.');
    }

    /**
     * GET /api/v1/stock-transfers/{stockTransfer}
     * Transfer detail.
     */
    public function showTransfer(Request $request, StockTransfer $stockTransfer): JsonResponse
    {
        abort_unless(
            $this->outletAccessService->canUseWarehouse($request->user(), $stockTransfer->sourceWarehouse)
            && $this->outletAccessService->canUseWarehouse($request->user(), $stockTransfer->destinationWarehouse),
            404
        );

        return $this->ok($this->transferDetail($stockTransfer));
    }

    /**
     * POST /api/v1/stock-transfers/{stockTransfer}/send
     * Send the transfer.
     */
    public function sendTransfer(Request $request, StockTransfer $stockTransfer): JsonResponse
    {
        abort_unless($this->outletAccessService->canUseWarehouse($request->user(), $stockTransfer->sourceWarehouse), 404);

        $this->stockTransferService->send($stockTransfer, $request->user()->id);

        return $this->ok($this->transferDetail($stockTransfer->fresh()), 'Transfer stok berhasil dikirim.');
    }

    /**
     * POST /api/v1/stock-transfers/{stockTransfer}/receive
     * Receive the transfer.
     */
    public function receiveTransfer(Request $request, StockTransfer $stockTransfer): JsonResponse
    {
        abort_unless($this->outletAccessService->canUseWarehouse($request->user(), $stockTransfer->destinationWarehouse), 404);

        $this->stockTransferService->receive($stockTransfer, $request->user()->id);

        return $this->ok($this->transferDetail($stockTransfer->fresh()), 'Transfer stok berhasil diterima.');
    }

    /**
     * POST /api/v1/stock-transfers/{stockTransfer}/cancel
     * Cancel the transfer.
     */
    public function cancelTransfer(Request $request, StockTransfer $stockTransfer): JsonResponse
    {
        abort_unless(
            $this->outletAccessService->canUseWarehouse($request->user(), $stockTransfer->sourceWarehouse)
            && $this->outletAccessService->canUseWarehouse($request->user(), $stockTransfer->destinationWarehouse),
            404
        );

        $this->stockTransferService->cancel($stockTransfer, $request->user()->id);

        return $this->ok($this->transferDetail($stockTransfer->fresh()), 'Transfer stok dibatalkan.');
    }

    protected function transferDetail(StockTransfer $stockTransfer): array
    {
        $stockTransfer->load([
            'sourceWarehouse:id,code,name',
            'destinationWarehouse:id,code,name',
            'items.product:id,title,sku',
            'creator:id,name',
        ]);

        return $stockTransfer->toArray();
    }
}
