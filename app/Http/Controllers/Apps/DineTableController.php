<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Models\DineArea;
use App\Models\DiningTable;
use App\Services\OutletAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class DineTableController extends Controller
{
    public function __construct(private readonly OutletAccessService $outletAccess) {}

    public function index(Request $request)
    {
        $outlet = $this->outletAccess->activeOutlet($request);
        $query = DiningTable::with('area')->whereHas('area', function ($query) use ($outlet) {
            $query->whereNull('outlet_id');
            if ($outlet) {
                $query->orWhere('outlet_id', $outlet->id);
            }
        });

        if ($request->filled('area_id')) {
            $query->where('dine_area_id', $request->area_id);
        }

        if ($request->boolean('is_active')) {
            $query->where('is_active', true);
        }

        $tables = $query->orderBy('dine_area_id')->orderBy('sort_order')->get();
        $areas = DineArea::whereNull('outlet_id')
            ->when($outlet, fn ($query) => $query->orWhere('outlet_id', $outlet->id))
            ->orderBy('sort_order')->get();

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
        $this->validateAreaAccess($validated['dine_area_id'] ?? null, $request);

        DiningTable::create($validated);

        return back()->with('success', 'Meja berhasil ditambahkan.');
    }

    public function update(Request $request, DiningTable $dineTable)
    {
        abort_unless($this->ownsTable($dineTable, $request), 404);
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
        $this->validateAreaAccess($validated['dine_area_id'] ?? null, $request);

        $dineTable->update($validated);

        return back()->with('success', 'Meja berhasil diperbarui.');
    }

    public function destroy(DiningTable $dineTable)
    {
        abort_unless($this->ownsTable($dineTable, request()), 404);
        $dineTable->delete();

        return back()->with('success', 'Meja berhasil dihapus.');
    }

    public function qr(DiningTable $dineTable)
    {
        abort_unless($this->ownsTable($dineTable, request()), 404);
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

    private function ownsTable(DiningTable $table, Request $request): bool
    {
        $outlet = $this->outletAccess->activeOutlet($request);
        $area = $table->area;

        return $area?->outlet_id === null || ($outlet && (int) $area->outlet_id === (int) $outlet->id);
    }

    private function validateAreaAccess(?int $areaId, Request $request): void
    {
        if (! $areaId) {
            return;
        }

        $area = DineArea::findOrFail($areaId);
        abort_unless($this->ownsArea($area, $request), 404);
    }

    private function ownsArea(DineArea $area, Request $request): bool
    {
        $outlet = $this->outletAccess->activeOutlet($request);

        return $area->outlet_id === null || ($outlet && (int) $area->outlet_id === (int) $outlet->id);
    }
}
