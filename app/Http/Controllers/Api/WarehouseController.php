<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\WarehouseResource;
use App\Http\Traits\ApiResponder;
use App\Models\Warehouse;
use App\Services\OutletAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WarehouseController extends Controller
{
    use ApiResponder;

    public function __construct(
        private readonly OutletAccessService $outletAccessService
    ) {}

    /**
     * GET /api/v1/warehouses
     */
    public function index(Request $request): JsonResponse
    {
        $allowed = $this->outletAccessService->warehousesFor($request->user());
        $warehouses = Warehouse::query()
            ->whereIn('id', $allowed->pluck('id'))
            ->when($request->string('search')->toString(), fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->orderBy('sort_order')
            ->orderBy('code')
            ->paginate($this->perPage());

        return $this->paginated($warehouses, WarehouseResource::collection($warehouses));
    }

    /**
     * POST /api/v1/warehouses
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('warehouses', 'code')],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', Rule::in(['main', 'branch', 'warehouse'])],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_active' => ['nullable', 'boolean'],
            'outlet_id' => ['nullable', 'integer', 'exists:outlets,id'],
        ]);

        $outlet = $validated['outlet_id'] ?? $request->user()->outlets()->first()?->id;
        abort_unless($request->user()->isSuperAdmin() || $outlet === null || $request->user()->outlets()->whereKey($outlet)->exists(), 403);

        $warehouse = Warehouse::create([
            ...$validated,
            'outlet_id' => $outlet,
            'is_active' => $validated['is_active'] ?? true,
            'sort_order' => 0,
        ]);

        return $this->created(new WarehouseResource($warehouse), 'Gudang berhasil dibuat');
    }

    /**
     * GET /api/v1/warehouses/{warehouse}
     */
    public function show(Request $request, Warehouse $warehouse): JsonResponse
    {
        abort_unless($this->outletAccessService->canUseWarehouse($request->user(), $warehouse), 404);

        return $this->ok(new WarehouseResource($warehouse));
    }

    /**
     * PUT/PATCH /api/v1/warehouses/{warehouse}
     */
    public function update(Request $request, Warehouse $warehouse): JsonResponse
    {
        abort_unless($this->outletAccessService->canUseWarehouse($request->user(), $warehouse), 404);

        $validated = $request->validate([
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('warehouses', 'code')->ignore($warehouse->id)],
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['nullable', Rule::in(['main', 'branch', 'warehouse'])],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (array_key_exists('is_active', $validated)
            && ! $validated['is_active']
            && $this->outletAccessService->hasOpenShift($warehouse)) {
            return $this->error('Tutup shift aktif sebelum menonaktifkan gudang.', 422);
        }

        $warehouse->update($validated);

        return $this->ok(new WarehouseResource($warehouse), 'Gudang berhasil diperbarui');
    }

    /**
     * DELETE /api/v1/warehouses/{warehouse}
     */
    public function destroy(Request $request, Warehouse $warehouse): JsonResponse
    {
        abort_unless($this->outletAccessService->canUseWarehouse($request->user(), $warehouse), 404);

        if ($this->outletAccessService->hasWarehouseHistory($warehouse)) {
            return $this->error('Gudang yang memiliki histori operasional tidak bisa dihapus.', 422);
        }

        $warehouse->delete();

        return $this->noContent();
    }
}
