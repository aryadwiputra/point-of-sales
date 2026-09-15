<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\OutletAccessService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class WarehouseController extends Controller
{
    public function __construct(private readonly OutletAccessService $outletAccessService) {}

    public function index()
    {
        $warehouses = $this->outletAccessService->warehousesFor(request()->user());

        return Inertia::render('Dashboard/Settings/Warehouses', [
            'warehouses' => $warehouses,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:warehouses,code'],
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in(['main', 'branch', 'warehouse'])],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:20'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ]);

        $outlet = $request->user()->isSuperAdmin()
            ? Outlet::find($request->input('outlet_id'))
            : $this->outletAccessService->activeOutlet($request);
        if ($outlet) {
            abort_unless($request->user()->isSuperAdmin() || $request->user()->outlets()->whereKey($outlet->id)->exists(), 403);
            $validated['outlet_id'] = $outlet->id;
        }

        $warehouse = Warehouse::create($validated);

        // Sync all existing products to this warehouse with 0 stock
        $productIds = Product::pluck('id');
        $warehouse->products()->syncWithPivotValues(
            $productIds,
            ['stock' => 0]
        );

        return back()->with('success', 'Gudang berhasil ditambahkan.');
    }

    public function update(Request $request, Warehouse $warehouse)
    {
        abort_unless($this->outletAccessService->canUseWarehouse($request->user(), $warehouse), 404);
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('warehouses', 'code')->ignore($warehouse->id)],
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in(['main', 'branch', 'warehouse'])],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:20'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ]);

        if (array_key_exists('is_active', $validated)
            && ! $validated['is_active']
            && $this->outletAccessService->hasOpenShift($warehouse)) {
            return back()->with('error', 'Tutup shift aktif sebelum menonaktifkan gudang.');
        }

        $warehouse->update($validated);

        return back()->with('success', 'Gudang berhasil diperbarui.');
    }

    public function destroy(Warehouse $warehouse)
    {
        abort_unless($this->outletAccessService->canUseWarehouse(request()->user(), $warehouse), 404);
        if ($warehouse->type === 'main') {
            return back()->with('error', 'Gudang utama tidak bisa dihapus.');
        }

        $totalStock = $warehouse->products()->sum('product_warehouse.stock');
        if ($totalStock > 0) {
            return back()->with('error', 'Gudang masih memiliki stok. Pindahkan stok terlebih dahulu.');
        }

        if ($this->outletAccessService->hasWarehouseHistory($warehouse)) {
            return back()->with('error', 'Gudang yang memiliki histori operasional tidak bisa dihapus. Nonaktifkan gudang sebagai gantinya.');
        }

        $warehouse->delete();

        return back()->with('success', 'Gudang berhasil dihapus.');
    }
}
