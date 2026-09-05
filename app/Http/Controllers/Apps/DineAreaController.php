<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Models\DineArea;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DineAreaController extends Controller
{
    public function index()
    {
        $areas = DineArea::with('tables')->orderBy('sort_order')->get();

        return Inertia::render('Dashboard/DineIn/Areas/Index', [
            'areas' => $areas,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'sort_order' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        DineArea::create($validated);

        return back()->with('success', 'Area berhasil ditambahkan.');
    }

    public function update(Request $request, DineArea $dineArea)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'sort_order' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $dineArea->update($validated);

        return back()->with('success', 'Area berhasil diperbarui.');
    }

    public function destroy(DineArea $dineArea)
    {
        if ($dineArea->tables()->exists()) {
            return back()->with('error', 'Area memiliki meja. Hapus atau pindahkan meja terlebih dahulu.');
        }

        $dineArea->delete();

        return back()->with('success', 'Area berhasil dihapus.');
    }
}
