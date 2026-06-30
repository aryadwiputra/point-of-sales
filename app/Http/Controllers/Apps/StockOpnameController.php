<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStockOpnameItemRequest;
use App\Http\Requests\StoreStockOpnameRequest;
use App\Http\Requests\UpdateStockOpnameItemRequest;
use App\Http\Requests\UpdateStockOpnameRequest;
use App\Models\Product;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\Warehouse;
use App\Services\AuditLogService;
use App\Services\StockMutationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StockOpnameController extends Controller
{
    public function __construct(
        private readonly StockMutationService $stockMutationService,
        private readonly AuditLogService $auditLogService
    ) {}

    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->input('search'),
            'status' => $request->input('status'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'warehouse_id' => $request->input('warehouse_id'),
        ];

        $stockOpnames = StockOpname::query()
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
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Dashboard/StockOpnames/Index', [
            'stockOpnames' => $stockOpnames,
            'filters' => $filters,
            'warehouses' => Warehouse::active()->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function create(): Response
    {
        $warehouses = Warehouse::active()->orderBy('sort_order')->orderBy('code')->get(['id', 'code', 'name']);

        return Inertia::render('Dashboard/StockOpnames/Create', [
            'warehouses' => $warehouses,
        ]);
    }

    public function store(StoreStockOpnameRequest $request): RedirectResponse
    {
        $stockOpname = StockOpname::create([
            'code' => $this->generateCode(),
            'warehouse_id' => $request->validated('warehouse_id'),
            'notes' => $request->validated('notes'),
            'status' => 'draft',
            'created_by' => $request->user()?->id,
        ]);

        return to_route('stock-opnames.show', $stockOpname);
    }

    public function show(Request $request, StockOpname $stockOpname): Response
    {
        $stockOpname->load([
            'creator:id,name',
            'finalizer:id,name',
            'items.product.category:id,name',
        ]);

        $productFilters = [
            'search' => $request->input('product_search', ''),
        ];

        $selectedProductIds = $stockOpname->items->pluck('product_id');

        $availableProducts = blank($productFilters['search'])
            ? collect()
            : Product::query()
                ->with('category:id,name')
                ->where(function ($builder) use ($productFilters) {
                    $builder
                        ->where('title', 'like', '%'.$productFilters['search'].'%')
                        ->orWhere('barcode', 'like', '%'.$productFilters['search'].'%')
                        ->orWhere('sku', 'like', '%'.$productFilters['search'].'%');
                })
                ->whereNotIn('id', $selectedProductIds)
                ->orderBy('title')
                ->limit(20)
                ->get()
                ->map(function ($product) use ($stockOpname) {
                    $pivotStock = 0;
                    if ($stockOpname->warehouse_id) {
                        $wh = $product->warehouses()->where('warehouse_id', $stockOpname->warehouse_id)->first();
                        $pivotStock = $wh?->pivot->stock ?? 0;
                    }

                    return [
                        ...$product->toArray(),
                        'warehouse_stock' => $pivotStock,
                    ];
                });

        return Inertia::render('Dashboard/StockOpnames/Show', [
            'stockOpname' => $stockOpname,
            'availableProducts' => $availableProducts,
            'productFilters' => $productFilters,
        ]);
    }

    public function update(UpdateStockOpnameRequest $request, StockOpname $stockOpname): RedirectResponse
    {
        $this->ensureDraft($stockOpname);

        $stockOpname->update($request->validated());

        return back()->with('success', 'Catatan stock opname berhasil diperbarui.');
    }

    public function storeItem(StoreStockOpnameItemRequest $request, StockOpname $stockOpname): RedirectResponse
    {
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

        $stockOpname->items()->create([
            'product_id' => $product->id,
            'system_stock' => $systemStock,
        ]);

        return back()->with('success', 'Produk berhasil ditambahkan ke stock opname.');
    }

    public function updateItem(
        UpdateStockOpnameItemRequest $request,
        StockOpname $stockOpname,
        StockOpnameItem $item
    ): RedirectResponse {
        $this->ensureDraft($stockOpname);
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

        return back()->with('success', 'Item stock opname berhasil diperbarui.');
    }

    public function finalize(Request $request, StockOpname $stockOpname): RedirectResponse
    {
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

                $stockBefore = (int) $product->stock;
                $stockAfter = (int) $item->physical_stock;

                $product->update([
                    'stock' => $stockAfter,
                ]);

                // Update pivot stock for warehouse
                if ($stockOpname->warehouse_id) {
                    \App\Models\ProductWarehouse::where([
                        'product_id' => $product->id,
                        'warehouse_id' => $stockOpname->warehouse_id,
                    ])->update(['stock' => $stockAfter]);
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
                'items' => $stockOpname->items->map(fn (StockOpnameItem $item) => [
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

        return back()->with('success', 'Stock opname berhasil difinalisasi.');
    }

    private function ensureDraft(StockOpname $stockOpname): void
    {
        if (! $stockOpname->isDraft()) {
            throw ValidationException::withMessages([
                'stock_opname' => 'Sesi stock opname yang sudah final tidak dapat diubah.',
            ]);
        }
    }

    private function ensureItemBelongsToOpname(StockOpname $stockOpname, StockOpnameItem $item): void
    {
        if ($item->stock_opname_id !== $stockOpname->id) {
            abort(404);
        }
    }

    private function generateCode(): string
    {
        do {
            $code = 'SO-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));
        } while (StockOpname::where('code', $code)->exists());

        return $code;
    }
}
