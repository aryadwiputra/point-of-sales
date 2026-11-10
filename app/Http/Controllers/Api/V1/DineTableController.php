<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Apps\DineTableController as AppsDineTableController;
use App\Http\Traits\ApiResponder;
use App\Models\DineArea;
use App\Models\DiningTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class DineTableController extends AppsDineTableController
{
    use ApiResponder;

    /**
     * GET /api/v1/dine-tables
     */
    public function listTables(Request $request): JsonResponse
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

        if ($request->input('is_active') === 'active') {
            $query->where('is_active', true);
        } elseif ($request->input('is_active') === 'inactive') {
            $query->where('is_active', false);
        }

        $tables = $query->orderBy('dine_area_id')->orderBy('sort_order')->get();
        $areas = DineArea::whereNull('outlet_id')
            ->when($outlet, fn ($query) => $query->orWhere('outlet_id', $outlet->id))
            ->orderBy('sort_order')
            ->get();

        return $this->ok([
            'tables' => $tables,
            'areas' => $areas,
            'filters' => [
                'area_id' => $request->area_id,
                'is_active' => $request->input('is_active', 'all'),
            ],
        ]);
    }

    /**
     * POST /api/v1/dine-tables
     */
    public function storeTable(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'dine_area_id' => ['nullable', 'exists:dine_areas,id'],
            'name' => ['required', 'string', 'max:100'],
            'capacity' => ['integer', 'min:0'],
            'pos_x' => ['integer', 'min:0', 'max:24'],
            'pos_y' => ['integer', 'min:0', 'max:14'],
            'shape' => [Rule::in(['circle', 'square'])],
            'sort_order' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $validated['token'] = (string) Str::uuid();
        $this->validateAreaAccess($validated['dine_area_id'] ?? null, $request);

        $table = DiningTable::create($validated);

        return $this->created($table);
    }

    /**
     * PATCH /api/v1/dine-tables/{dineTable}
     */
    public function updateTable(Request $request, DiningTable $dineTable): JsonResponse
    {
        abort_unless($this->ownsTable($dineTable, $request), 404);
        $validated = $request->validate([
            'dine_area_id' => ['nullable', 'exists:dine_areas,id'],
            'name' => ['required', 'string', 'max:100'],
            'capacity' => ['integer', 'min:0'],
            'pos_x' => ['integer', 'min:0', 'max:24'],
            'pos_y' => ['integer', 'min:0', 'max:14'],
            'shape' => [Rule::in(['circle', 'square'])],
            'sort_order' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);
        $this->validateAreaAccess($validated['dine_area_id'] ?? null, $request);

        $dineTable->update($validated);

        return $this->ok($dineTable->fresh('area'));
    }

    /**
     * DELETE /api/v1/dine-tables/{dineTable}
     */
    public function destroyTable(Request $request, DiningTable $dineTable): JsonResponse
    {
        abort_unless($this->ownsTable($dineTable, $request), 404);
        $dineTable->delete();

        return $this->noContent();
    }

    /**
     * GET /api/v1/dine-tables/{dineTable}/qr
     */
    public function qrImage(Request $request, DiningTable $dineTable)
    {
        abort_unless($this->ownsTable($dineTable, $request), 404);
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
