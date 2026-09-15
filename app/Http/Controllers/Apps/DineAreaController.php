<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Models\DineArea;
use App\Services\OutletAccessService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DineAreaController extends Controller
{
    public function __construct(private readonly OutletAccessService $outletAccess) {}

    public function index()
    {
        $outlet = $this->outletAccess->activeOutlet(request());
        $areas = DineArea::with('tables')
            ->where(function ($query) use ($outlet) {
                $query->whereNull('outlet_id');
                if ($outlet) {
                    $query->orWhere('outlet_id', $outlet->id);
                }
            })
            ->orderBy('sort_order')->get();

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

        $validated['outlet_id'] = $this->outletAccess->activeOutlet($request)?->id;
        DineArea::create($validated);

        return back()->with('success', 'Area berhasil ditambahkan.');
    }

    public function update(Request $request, DineArea $dineArea)
    {
        abort_unless($this->ownsArea($dineArea, $request), 404);
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
        abort_unless($this->ownsArea($dineArea, request()), 404);
        if ($dineArea->tables()->exists()) {
            return back()->with('error', 'Area memiliki meja. Hapus atau pindahkan meja terlebih dahulu.');
        }

        $dineArea->delete();

        return back()->with('success', 'Area berhasil dihapus.');
    }

    private function ownsArea(DineArea $area, Request $request): bool
    {
        $outlet = $this->outletAccess->activeOutlet($request);

        return $area->outlet_id === null || ($outlet && (int) $area->outlet_id === (int) $outlet->id);
    }
}
