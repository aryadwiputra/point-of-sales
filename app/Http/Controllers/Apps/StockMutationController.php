<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Outlet;
use App\Models\StockMutation;
use App\Services\OutletAccessService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockMutationController extends Controller
{
    public function __construct(
        private readonly OutletAccessService $outletAccessService
    ) {}

    public function index(Request $request): Response
    {
        $filters = [
            'product_id' => $request->input('product_id'),
            'mutation_type' => $request->input('mutation_type'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'warehouse_id' => $request->input('warehouse_id'),
        ];

        $stockMutations = StockMutation::query()
            ->with(['product:id,title,barcode,sku', 'creator:id,name', 'warehouse:id,code,name'])
            ->when($filters['product_id'], fn ($query, $productId) => $query->where('product_id', $productId))
            ->when($filters['mutation_type'], fn ($query, $mutationType) => $query->where('mutation_type', $mutationType))
            ->when($filters['date_from'], fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'], fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->when($filters['warehouse_id'], fn ($query, $warehouseId) => $query->where('warehouse_id', $warehouseId))
            ->where(function ($query) use ($request) {
                $warehouseIds = $this->outletAccessService->warehousesFor($request->user())->pluck('id');
                if ($warehouseIds->isEmpty()) {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->whereIn('warehouse_id', $warehouseIds);
                    if (Outlet::active()->count() <= 1) {
                        $query->orWhereNull('warehouse_id');
                    }
                }
            })
            ->latest()
            ->paginate($this->perPage())->withQueryString()
            ->withQueryString();

        return Inertia::render('Dashboard/StockMutations/Index', [
            'stockMutations' => $stockMutations,
            'products' => Product::query()->orderBy('title')->get(['id', 'title', 'barcode', 'sku']),
            'warehouses' => $this->outletAccessService->warehousesFor($request->user()),
            'filters' => $filters,
        ]);
    }
}
