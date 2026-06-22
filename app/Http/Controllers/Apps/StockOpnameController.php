<?php

declare(strict_types=1);

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Http\Requests\StockOpname\FinalizeStockOpnameRequest;
use App\Http\Requests\StockOpname\IndexStockOpnameRequest;
use App\Http\Requests\StockOpname\ShowStockOpnameRequest;
use App\Http\Requests\StockOpname\StoreStockOpnameItemRequest;
use App\Http\Requests\StockOpname\StoreStockOpnameRequest;
use App\Http\Requests\StockOpname\UpdateStockOpnameItemRequest;
use App\Http\Requests\StockOpname\UpdateStockOpnameRequest;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Services\StockOpnames\CreateStockOpnameService;
use App\Services\StockOpnames\FinalizeStockOpnameService;
use App\Services\StockOpnames\StockOpnameIndexQueryService;
use App\Services\StockOpnames\StockOpnameShowQueryService;
use App\Services\StockOpnames\StoreStockOpnameItemService;
use App\Services\StockOpnames\UpdateStockOpnameItemService;
use App\Services\StockOpnames\UpdateStockOpnameService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class StockOpnameController extends Controller
{
    public function index(IndexStockOpnameRequest $request, StockOpnameIndexQueryService $service): Response
    {
        return Inertia::render('Dashboard/StockOpnames/Index', $service->execute($request->filters()));
    }

    public function create(): Response
    {
        return Inertia::render('Dashboard/StockOpnames/Create');
    }

    public function store(StoreStockOpnameRequest $request, CreateStockOpnameService $service): RedirectResponse
    {
        $stockOpname = $service->execute($request->validated(), $request->user()?->id);

        return to_route('stock-opnames.show', $stockOpname);
    }

    public function show(
        ShowStockOpnameRequest $request,
        StockOpname $stockOpname,
        StockOpnameShowQueryService $service
    ): Response {
        return Inertia::render(
            'Dashboard/StockOpnames/Show',
            $service->execute($stockOpname, $request->productFilters())
        );
    }

    public function update(
        UpdateStockOpnameRequest $request,
        StockOpname $stockOpname,
        UpdateStockOpnameService $service
    ): RedirectResponse {
        $service->execute($stockOpname, $request->validated());

        return back()->with('success', 'Catatan stock opname berhasil diperbarui.');
    }

    public function storeItem(
        StoreStockOpnameItemRequest $request,
        StockOpname $stockOpname,
        StoreStockOpnameItemService $service
    ): RedirectResponse {
        $service->execute($stockOpname, $request->integer('product_id'));

        return back()->with('success', 'Produk berhasil ditambahkan ke stock opname.');
    }

    public function updateItem(
        UpdateStockOpnameItemRequest $request,
        StockOpname $stockOpname,
        StockOpnameItem $item,
        UpdateStockOpnameItemService $service
    ): RedirectResponse {
        $service->execute($stockOpname, $item, $request->validated());

        return back()->with('success', 'Item stock opname berhasil diperbarui.');
    }

    public function finalize(
        FinalizeStockOpnameRequest $request,
        StockOpname $stockOpname,
        FinalizeStockOpnameService $service
    ): RedirectResponse {
        $service->execute($stockOpname, $request->user()?->id);

        return back()->with('success', 'Stock opname berhasil difinalisasi.');
    }
}
