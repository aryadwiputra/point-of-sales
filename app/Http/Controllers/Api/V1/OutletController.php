<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Apps\OutletController as AppsOutletController;
use App\Http\Requests\StoreOutletRequest;
use App\Http\Requests\UpdateOutletRequest;
use App\Http\Traits\ApiResponder;
use App\Models\Outlet;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OutletController extends AppsOutletController
{
    use ApiResponder;

    /**
     * GET /api/v1/outlets
     */
    public function listOutlets(Request $request): JsonResponse
    {
        $outlets = $this->outletAccessService->accessibleOutlets($request->user())
            ->load(['warehouses' => fn ($query) => $query->orderBy('sort_order')->orderBy('code')]);

        return $this->ok($outlets);
    }

    /**
     * POST /api/v1/outlets
     */
    public function storeOutlet(StoreOutletRequest $request): JsonResponse
    {
        $data = $request->validated();

        $outlet = DB::transaction(function () use ($request, $data): Outlet {
            $outlet = Outlet::create([
                'code' => strtoupper($data['code']),
                'name' => $data['name'],
                'is_active' => true,
                'is_sales_enabled' => (bool) ($data['is_sales_enabled'] ?? true),
                'address' => $data['address'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
            ]);

            $warehouse = $outlet->warehouses()->create([
                'code' => strtoupper($data['warehouse_code']),
                'name' => $data['warehouse_name'],
                'type' => $outlet->is_sales_enabled ? 'branch' : 'warehouse',
                'address' => $outlet->address,
                'phone' => $outlet->phone,
                'is_active' => true,
                'sort_order' => 0,
            ]);

            $warehouse->products()->syncWithPivotValues(Product::pluck('id'), ['stock' => 0]);

            if (! $request->user()->isSuperAdmin()) {
                $request->user()->outlets()->syncWithoutDetaching([
                    $outlet->id => ['is_default' => true],
                ]);
            }

            return $outlet;
        });

        return $this->created($outlet->load(['warehouses' => fn ($query) => $query->orderBy('sort_order')->orderBy('code')]));
    }

    /**
     * PUT /api/v1/outlets/{outlet}
     */
    public function updateOutlet(UpdateOutletRequest $request, Outlet $outlet): JsonResponse
    {
        $this->ensureAccess($request->user(), $outlet);
        $data = $request->validated();

        $isActive = (bool) ($data['is_active'] ?? $outlet->is_active);
        $isSalesEnabled = strtoupper($data['code']) === 'PUSAT'
            ? false
            : (bool) ($data['is_sales_enabled'] ?? $outlet->is_sales_enabled);

        if ((! $isActive || ! $isSalesEnabled) && $this->hasOpenShift($outlet)) {
            throw ValidationException::withMessages([
                'outlet' => 'Tutup semua shift aktif sebelum menonaktifkan outlet atau penjualan.',
            ]);
        }

        $outlet->update([
            'code' => strtoupper($data['code']),
            'name' => $data['name'],
            'address' => $data['address'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'is_active' => $isActive,
            'is_sales_enabled' => $isSalesEnabled,
        ]);

        return $this->ok($outlet->fresh()->load(['warehouses' => fn ($query) => $query->orderBy('sort_order')->orderBy('code')]));
    }

    /**
     * DELETE /api/v1/outlets/{outlet}
     */
    public function destroyOutlet(Request $request, Outlet $outlet): JsonResponse
    {
        $this->ensureAccess($request->user(), $outlet);

        if ($outlet->code === 'PUSAT' || $this->hasOperationalHistory($outlet)) {
            throw ValidationException::withMessages([
                'outlet' => 'Outlet yang sudah digunakan tidak dapat dihapus. Nonaktifkan outlet sebagai gantinya.',
            ]);
        }

        $outlet->delete();

        return $this->noContent();
    }
}
