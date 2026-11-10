<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Apps\UnitController as AppsUnitController;
use App\Http\Traits\ApiResponder;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UnitController extends AppsUnitController
{
    use ApiResponder;

    /**
     * GET /api/v1/units
     */
    public function listUnits(): JsonResponse
    {
        return $this->ok(Unit::withCount('products')->orderBy('code')->get());
    }

    /**
     * POST /api/v1/units
     */
    public function storeUnit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:10', 'unique:units,code'],
            'name' => ['required', 'string', 'max:50'],
            'symbol' => ['required', 'string', 'max:10'],
        ]);

        return $this->created(Unit::create($validated));
    }

    /**
     * PUT /api/v1/units/{unit}
     */
    public function updateUnit(Request $request, Unit $unit): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:10', Rule::unique('units', 'code')->ignore($unit->id)],
            'name' => ['required', 'string', 'max:50'],
            'symbol' => ['required', 'string', 'max:10'],
        ]);

        $unit->update($validated);

        return $this->ok($unit->fresh());
    }

    /**
     * DELETE /api/v1/units/{unit}
     */
    public function destroyUnit(Unit $unit): JsonResponse
    {
        if ($unit->products()->exists()) {
            throw ValidationException::withMessages([
                'unit' => 'Satuan masih dipakai produk. Lepaskan dari produk terlebih dahulu.',
            ]);
        }

        $unit->delete();

        return $this->noContent();
    }
}
