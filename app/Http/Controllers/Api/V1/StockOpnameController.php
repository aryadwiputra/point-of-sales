<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Apps\StockOpnameController as AppsStockOpnameController;
use App\Http\Requests\StoreStockOpnameItemRequest;
use App\Http\Requests\StoreStockOpnameRequest;
use App\Http\Requests\UpdateStockOpnameItemRequest;
use App\Http\Requests\UpdateStockOpnameRequest;
use App\Http\Traits\ApiResponder;
use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockOpnameController extends AppsStockOpnameController
{
    use ApiResponder;

    /**
     * GET /api/v1/stock-opnames
     * Paginated stock opname list.
     */
    public function listOpnames(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->input('search'),
            'status' => $request->input('status'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'warehouse_id' => $request->input('warehouse_id'),
        ];

        $warehouseIds = $this->outletAccessService->warehousesFor($request->user())->pluck('id');
        $stockOpnames = StockOpname::query()
            ->whereIn('warehouse_id', $warehouseIds)
            ->with(['creator:id,name', 'finalizer:id,name', 'warehouse:id,code,name'])
            ->when($filters['search'], function ($query, $search) {
                $query->where(function ($builder) use ($search) {
                    $builder
                        ->where('code', 'like', '%'.$search.'%')
                        ->orWhere('notes', 'like', '%'.$search.'%');
                });
            })
            ->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
            ->when($filters['date_from'], fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'], fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->when($filters['warehouse_id'], fn ($query, $warehouseId) => $query->where('warehouse_id', $warehouseId))
            ->withCount('items')
            ->latest()
            ->paginate($this->perPage())->withQueryString();

        return $this->paginated($stockOpnames);
    }

    /**
     * GET /api/v1/stock-opnames/create
     * Reference warehouses for creating an opname session.
     */
    public function createData(Request $request): JsonResponse
    {
        return $this->ok([
            'warehouses' => $this->outletAccessService->warehousesFor($request->user()),
        ]);
    }

    /**
     * POST /api/v1/stock-opnames
     * Create a draft opname session.
     */
    public function storeOpname(StoreStockOpnameRequest $request): JsonResponse
    {
        $this->ensureWarehouseAccess($request->user(), $request->validated('warehouse_id'));

        $stockOpname = StockOpname::create([
            'code' => $this->generateCode(),
            'warehouse_id' => $request->validated('warehouse_id'),
            'notes' => $request->validated('notes'),
            'status' => 'draft',
            'created_by' => $request->user()?->id,
        ]);

        return $this->created($stockOpname->fresh()->load(['warehouse:id,code,name']), 'Sesi stock opname berhasil dibuat.');
    }

    /**
     * GET /api/v1/stock-opnames/{stockOpname}
     * Opname detail with items.
     */
    public function showOpname(Request $request, StockOpname $stockOpname): JsonResponse
    {
        $this->ensureWarehouseAccess($request->user(), $stockOpname->warehouse_id);
        $stockOpname->load([
            'creator:id,name',
            'finalizer:id,name',
            'items.product.category:id,name',
        ]);

        return $this->ok($stockOpname);
    }

    /**
     * PATCH /api/v1/stock-opnames/{stockOpname}
     * Update opname notes.
     */
    public function updateOpname(UpdateStockOpnameRequest $request, StockOpname $stockOpname): JsonResponse
    {
        $this->ensureWarehouseAccess($request->user(), $stockOpname->warehouse_id);
        $this->ensureDraft($stockOpname);

        $stockOpname->update($request->validated());

        return $this->ok($stockOpname->fresh(), 'Catatan stock opname berhasil diperbarui.');
    }

    /**
     * POST /api/v1/stock-opnames/{stockOpname}/items
     * Add a product to the opname session.
     */
    public function storeOpnameItem(StoreStockOpnameItemRequest $request, StockOpname $stockOpname): JsonResponse
    {
        $this->ensureWarehouseAccess($request->user(), $stockOpname->warehouse_id);
        $this->ensureDraft($stockOpname);

        $product = Product::findOrFail($request->validated('product_id'));

        if ($stockOpname->items()->where('product_id', $product->id)->exists()) {
            throw ValidationException::withMessages([
                'product_id' => 'Produk sudah ada di sesi stock opname ini.',
            ]);
        }

        $systemStock = 0;
        if ($stockOpname->warehouse_id) {
            $wh = $product->warehouses()->where('warehouse_id', $stockOpname->warehouse_id)->first();
            $systemStock = $wh?->pivot->stock ?? 0;
        }

        $item = $stockOpname->items()->create([
            'product_id' => $product->id,
            'system_stock' => $systemStock,
        ]);

        return $this->created($item->load('product:id,title,sku,barcode'), 'Produk berhasil ditambahkan ke stock opname.');
    }

    /**
     * PATCH /api/v1/stock-opnames/{stockOpname}/items/{item}
     * Record physical stock and difference for an opname item.
     */
    public function updateOpnameItem(
        UpdateStockOpnameItemRequest $request,
        StockOpname $stockOpname,
        $item
    ): JsonResponse {
        $this->ensureWarehouseAccess($request->user(), $stockOpname->warehouse_id);
        $this->ensureDraft($stockOpname);

        $item = StockOpnameItem::findOrFail($item);
        $this->ensureItemBelongsToOpname($stockOpname, $item);

        $validated = $request->validated();
        $physicalStock = $validated['physical_stock'] ?? null;
        $difference = $physicalStock !== null
            ? $physicalStock - $item->system_stock
            : null;

        $adjustmentReason = $validated['adjustment_reason'] ?? null;

        if ($difference !== null && $difference !== 0 && blank($adjustmentReason)) {
            throw ValidationException::withMessages([
                'adjustment_reason' => 'Alasan adjustment wajib diisi jika ada selisih stok.',
            ]);
        }

        if ($difference === 0) {
            $adjustmentReason = null;
        }

        $item->update([
            'physical_stock' => $physicalStock,
            'difference' => $difference,
            'adjustment_reason' => $adjustmentReason,
        ]);

        return $this->ok($item->fresh(), 'Item stock opname berhasil diperbarui.');
    }

    /**
     * POST /api/v1/stock-opnames/{stockOpname}/finalize
     * Apply stock adjustments and finalize the opname.
     */
    public function finalizeOpname(Request $request, StockOpname $stockOpname): JsonResponse
    {
        $this->ensureWarehouseAccess($request->user(), $stockOpname->warehouse_id);
        $this->ensureDraft($stockOpname);

        $stockOpname->load('items.product');
        $beforeStatus = $stockOpname->status;

        foreach ($stockOpname->items as $item) {
            if ($item->difference !== null && $item->difference !== 0 && blank($item->adjustment_reason)) {
                throw ValidationException::withMessages([
                    'finalize' => 'Masih ada item selisih yang belum memiliki alasan adjustment.',
                ]);
            }
        }

        DB::transaction(function () use ($request, $stockOpname) {
            foreach ($stockOpname->items as $item) {
                if ($item->physical_stock === null) {
                    continue;
                }

                $product = $item->product()->lockForUpdate()->first();

                if (! $product) {
                    continue;
                }

                $stockAfter = (int) $item->physical_stock;

                if ($stockOpname->warehouse_id) {
                    // ponytail: opname is per-warehouse — only touch that warehouse's pivot, then recompute the legacy aggregate
                    $before = ProductWarehouse::where([
                        'product_id' => $product->id,
                        'warehouse_id' => $stockOpname->warehouse_id,
                    ])->lockForUpdate()->first();

                    $stockBefore = (int) ($before?->stock ?? 0);

                    ProductWarehouse::updateOrCreate(
                        ['product_id' => $product->id, 'warehouse_id' => $stockOpname->warehouse_id],
                        ['stock' => $stockAfter]
                    );

                    $product->update(['stock' => $product->stockTotal()]);
                } else {
                    $stockBefore = (int) $product->stock;
                    $product->update(['stock' => $stockAfter]);
                }

                $this->stockMutationService->recordStockOpnameAdjustment(
                    product: $product,
                    stockOpname: $stockOpname,
                    stockBefore: $stockBefore,
                    stockAfter: $stockAfter,
                    reason: $item->adjustment_reason,
                    userId: $request->user()?->id,
                );
            }

            $stockOpname->update([
                'status' => 'finalized',
                'finalized_by' => $request->user()?->id,
                'finalized_at' => now(),
            ]);
        });

        $stockOpname->refresh();
        $stockOpname->load('items.product');

        $this->auditLogService->log(
            event: 'stock.opname.finalized',
            module: 'stock',
            auditable: $stockOpname,
            description: 'Stock opname difinalisasi.',
            before: ['status' => $beforeStatus],
            after: ['status' => $stockOpname->status],
            meta: [
                'code' => $stockOpname->code,
                'notes' => $stockOpname->notes,
                'items' => $stockOpname->items->map(fn ($item) => [
                    'product_id' => $item->product_id,
                    'product_title' => $item->product?->title,
                    'stock_before' => (int) $item->system_stock,
                    'stock_after' => $item->physical_stock !== null ? (int) $item->physical_stock : null,
                    'difference' => $item->difference !== null ? (int) $item->difference : null,
                    'reason' => $item->adjustment_reason,
                    'reference' => $stockOpname->code,
                ])->values()->all(),
            ],
        );

        return $this->ok($stockOpname->load(['warehouse:id,code,name', 'items.product:id,title,sku']), 'Stock opname berhasil difinalisasi.');
    }
}
