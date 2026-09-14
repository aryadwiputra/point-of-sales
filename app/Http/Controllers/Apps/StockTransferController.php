<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Services\OutletAccessService;
use App\Services\StockTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockTransferController extends Controller
{
    public function __construct(
        private readonly StockTransferService $stockTransferService,
        private readonly OutletAccessService $outletAccessService
    ) {}

    public function index(): Response
    {
        $warehouses = $this->outletAccessService->warehousesFor(request()->user());
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

        return Inertia::render('Dashboard/StockTransfers/Index', [
            'transfers' => $transfers,
        ]);
    }

    public function create(): Response
    {
        $warehouses = $this->outletAccessService->warehousesFor(request()->user());
        $products = Product::orderBy('title')->get(['id', 'title', 'sku', 'stock']);

        return Inertia::render('Dashboard/StockTransfers/Create', [
            'warehouses' => $warehouses,
            'products' => $products,
        ]);
    }

    public function store(Request $request): RedirectResponse
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

        return redirect()
            ->route('stock-transfers.show', $transfer)
            ->with('success', 'Transfer stok berhasil dibuat.');
    }

    public function show(StockTransfer $stockTransfer): Response
    {
        $stockTransfer->load([
            'sourceWarehouse:id,code,name',
            'destinationWarehouse:id,code,name',
            'items.product:id,title,sku',
            'creator:id,name',
        ]);

        return Inertia::render('Dashboard/StockTransfers/Show', [
            'transfer' => $stockTransfer,
        ]);
    }

    public function send(Request $request, StockTransfer $stockTransfer): RedirectResponse
    {
        $this->stockTransferService->send($stockTransfer, $request->user()->id);

        return back()->with('success', 'Transfer stok berhasil dikirim.');
    }

    public function receive(Request $request, StockTransfer $stockTransfer): RedirectResponse
    {
        $this->stockTransferService->receive($stockTransfer, $request->user()->id);

        return back()->with('success', 'Transfer stok berhasil diterima.');
    }

    public function cancel(Request $request, StockTransfer $stockTransfer): RedirectResponse
    {
        $this->stockTransferService->cancel($stockTransfer, $request->user()->id);

        return back()->with('success', 'Transfer stok dibatalkan.');
    }
}
