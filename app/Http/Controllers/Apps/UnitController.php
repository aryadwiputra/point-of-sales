<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class UnitController extends Controller
{
    public function index()
    {
        $units = Unit::withCount('products')->orderBy('code')->get();

        return Inertia::render('Dashboard/Settings/Units', [
            'units' => $units,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:10', 'unique:units,code'],
            'name' => ['required', 'string', 'max:50'],
            'symbol' => ['required', 'string', 'max:10'],
        ]);

        Unit::create($validated);

        return back()->with('success', 'Satuan berhasil ditambahkan.');
    }

    public function update(Request $request, Unit $unit)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:10', Rule::unique('units', 'code')->ignore($unit->id)],
            'name' => ['required', 'string', 'max:50'],
            'symbol' => ['required', 'string', 'max:10'],
        ]);

        $unit->update($validated);

        return back()->with('success', 'Satuan berhasil diperbarui.');
    }

    public function destroy(Unit $unit)
    {
        if ($unit->products()->exists()) {
            return back()->with('error', 'Satuan masih dipakai produk. Lepaskan dari produk terlebih dahulu.');
        }

        $unit->delete();

        return back()->with('success', 'Satuan berhasil dihapus.');
    }
}
