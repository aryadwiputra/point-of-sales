<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class WarehouseController extends Controller
{
    public function index()
    {
        $warehouses = Warehouse::orderBy('sort_order')->orderBy('code')->get();

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

        $warehouse = Warehouse::create($validated);

        // Sync all existing products to this warehouse with 0 stock
        $productIds = \App\Models\Product::pluck('id');
        $warehouse->products()->syncWithPivotValues(
            $productIds,
            ['stock' => 0]
        );

        return back()->with('success', 'Gudang berhasil ditambahkan.');
    }

    public function update(Request $request, Warehouse $warehouse)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('warehouses', 'code')->ignore($warehouse->id)],
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in(['main', 'branch', 'warehouse'])],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:20'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ]);

        $warehouse->update($validated);

        return back()->with('success', 'Gudang berhasil diperbarui.');
    }

    public function destroy(Warehouse $warehouse)
    {
        if ($warehouse->type === 'main') {
            return back()->with('error', 'Gudang utama tidak bisa dihapus.');
        }

        $totalStock = $warehouse->products()->sum('product_warehouse.stock');
        if ($totalStock > 0) {
            return back()->with('error', 'Gudang masih memiliki stok. Pindahkan stok terlebih dahulu.');
        }

        $warehouse->delete();

        return back()->with('success', 'Gudang berhasil dihapus.');
    }
}
