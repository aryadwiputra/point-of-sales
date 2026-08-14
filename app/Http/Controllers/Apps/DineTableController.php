<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Models\DineArea;
use App\Models\DiningTable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class DineTableController extends Controller
{
    public function index(Request $request)
    {
        $query = DiningTable::with('area');

        if ($request->filled('area_id')) {
            $query->where('dine_area_id', $request->area_id);
        }

        if ($request->boolean('is_active')) {
            $query->where('is_active', true);
        }

        $tables = $query->orderBy('dine_area_id')->orderBy('sort_order')->get();
        $areas = DineArea::orderBy('sort_order')->get();

        return Inertia::render('Dashboard/DineIn/Tables/Index', [
            'tables' => $tables,
            'areas' => $areas,
            'filters' => [
                'area_id' => $request->area_id,
                'is_active' => $request->boolean('is_active'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'dine_area_id' => ['nullable', 'exists:dine_areas,id'],
            'name' => ['required', 'string', 'max:100'],
            'capacity' => ['integer', 'min:0'],
            'shape' => [Rule::in(['circle', 'square'])],
            'sort_order' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $validated['token'] = (string) Str::uuid();

        DiningTable::create($validated);

        return back()->with('success', 'Meja berhasil ditambahkan.');
    }

    public function update(Request $request, DiningTable $dineTable)
    {
        $validated = $request->validate([
            'dine_area_id' => ['nullable', 'exists:dine_areas,id'],
            'name' => ['required', 'string', 'max:100'],
            'capacity' => ['integer', 'min:0'],
            'pos_x' => ['integer', 'min:0'],
            'pos_y' => ['integer', 'min:0'],
            'shape' => [Rule::in(['circle', 'square'])],
            'sort_order' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $dineTable->update($validated);

        return back()->with('success', 'Meja berhasil diperbarui.');
    }

    public function destroy(DiningTable $dineTable)
    {
        $dineTable->delete();

        return back()->with('success', 'Meja berhasil dihapus.');
    }

    public function qr(DiningTable $dineTable)
    {
        $url = config('app.url').'/dine/'.$dineTable->token;

        $png = QrCode::format('png')
            ->size(300)
            ->margin(2)
            ->generate($url);

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'inline; filename="qr-'.$dineTable->id.'.png"',
        ]);
    }
}
