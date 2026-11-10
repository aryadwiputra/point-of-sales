<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Apps\DineAreaController as AppsDineAreaController;
use App\Http\Traits\ApiResponder;
use App\Models\DineArea;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DineAreaController extends AppsDineAreaController
{
    use ApiResponder;

    /**
     * GET /api/v1/dine-areas
     */
    public function listAreas(Request $request): JsonResponse
    {
        $outlet = $this->outletAccess->activeOutlet($request);

        $areas = DineArea::with('tables')
            ->where(function ($query) use ($outlet) {
                $query->whereNull('outlet_id');
                if ($outlet) {
                    $query->orWhere('outlet_id', $outlet->id);
                }
            })
            ->orderBy('sort_order')
            ->get();

        return $this->ok(['areas' => $areas]);
    }

    /**
     * GET /api/v1/dine-areas/{dineArea}
     */
    public function showArea(Request $request, DineArea $dineArea): JsonResponse
    {
        abort_unless($this->ownsArea($dineArea, $request), 404);

        return $this->ok($dineArea->load('tables'));
    }

    /**
     * POST /api/v1/dine-areas
     */
    public function storeArea(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'sort_order' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $validated['outlet_id'] = $this->outletAccess->activeOutlet($request)?->id;
        $area = DineArea::create($validated);

        return $this->created($area);
    }

    /**
     * PATCH /api/v1/dine-areas/{dineArea}
     */
    public function updateArea(Request $request, DineArea $dineArea): JsonResponse
    {
        abort_unless($this->ownsArea($dineArea, $request), 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'sort_order' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $dineArea->update($validated);

        return $this->ok($dineArea->fresh('tables'));
    }

    /**
     * DELETE /api/v1/dine-areas/{dineArea}
     */
    public function destroyArea(Request $request, DineArea $dineArea): JsonResponse
    {
        abort_unless($this->ownsArea($dineArea, $request), 404);

        if ($dineArea->tables()->exists()) {
            return $this->error('Area memiliki meja. Hapus atau pindahkan meja terlebih dahulu.', 422);
        }

        $dineArea->delete();

        return $this->noContent();
    }
}
