<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\KitchenStation;
use App\Models\KitchenStationCategory;
use App\Models\Product;
use App\Models\ProductKitchenStationOverride;
use App\Models\Setting;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\OutletAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class KitchenStationController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly OutletAccessService $outletAccessService,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $outlets = $this->outletAccessService->accessibleOutlets($user);
        $outletId = $request->integer('outlet_id') ?: $outlets->first()?->id;

        if ($outletId && ! $outlets->contains('id', $outletId)) {
            abort(403);
        }

        $stations = KitchenStation::query()
            ->with(['categories:id,name', 'users:id,name', 'productOverrides.product:id,title'])
            ->when($outletId, fn ($query) => $query->where('outlet_id', $outletId), fn ($query) => $query->whereNull('outlet_id'))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $mappedCategoryIds = $outletId
            ? KitchenStationCategory::where('outlet_id', $outletId)->pluck('category_id')
            : collect();
        $overriddenProductIds = $outletId
            ? ProductKitchenStationOverride::where('outlet_id', $outletId)->pluck('product_id')
            : collect();

        $unmappedCategories = $outletId
            ? Category::query()
                ->whereNotIn('id', $mappedCategoryIds)
                ->whereHas('products', fn ($query) => $query->whereNotIn('id', $overriddenProductIds))
                ->orderBy('name')
                ->get(['id', 'name'])
            : collect();

        return Inertia::render('Dashboard/Kitchen/Stations/Index', [
            'outlets' => $outlets->map(fn ($outlet) => ['id' => $outlet->id, 'code' => $outlet->code, 'name' => $outlet->name])->values(),
            'stations' => $stations->map(fn (KitchenStation $station) => [
                'id' => $station->id,
                'outlet_id' => $station->outlet_id,
                'name' => $station->name,
                'sort_order' => $station->sort_order,
                'is_active' => $station->is_active,
                'category_ids' => $station->categories->pluck('id')->values(),
                'user_ids' => $station->users->pluck('id')->values(),
                'product_ids' => $station->productOverrides->pluck('product_id')->values(),
            ])->values(),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'users' => User::permission('kitchen-access')->orderBy('name')->get(['id', 'name', 'email']),
            'products' => Product::orderBy('title')->get(['id', 'title', 'category_id']),
            'unmappedCategories' => $unmappedCategories,
            'kitchenEnabled' => Setting::getBool('kitchen_enabled', true),
            'filters' => ['outlet_id' => $outletId],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, null);
        $station = $this->syncStation($data, null);

        $this->auditLogService->log('kitchen.station.created', 'kitchen', $station, 'Station dapur dibuat.', null, $station->toArray());

        return back()->with('success', 'Station dapur dibuat.');
    }

    public function update(Request $request, KitchenStation $kitchenStation)
    {
        $before = $kitchenStation->toArray();
        $data = $this->validated($request, $kitchenStation);
        $station = $this->syncStation($data, $kitchenStation);

        $this->auditLogService->log('kitchen.station.updated', 'kitchen', $station, 'Station dapur diperbarui.', $before, $station->toArray());

        return back()->with('success', 'Station dapur diperbarui.');
    }

    public function destroy(KitchenStation $kitchenStation)
    {
        if ($kitchenStation->tickets()->exists()) {
            return back()->with('error', 'Station memiliki tiket dapur. Nonaktifkan station sebagai gantinya.');
        }

        $this->auditLogService->log('kitchen.station.deleted', 'kitchen', $kitchenStation, 'Station dapur dihapus.', $kitchenStation->toArray());
        $kitchenStation->delete();

        return back()->with('success', 'Station dapur dihapus.');
    }

    private function validated(Request $request, ?KitchenStation $station): array
    {
        $data = $request->validate([
            'outlet_id' => ['nullable', 'integer', 'exists:outlets,id'],
            'name' => ['required', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'category_ids' => ['array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'user_ids' => ['array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'product_ids' => ['array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
        ]);

        $outletId = $data['outlet_id'] ?? null;

        if ($outletId && ! $this->outletAccessService->accessibleOutlets($request->user())->contains('id', $outletId)) {
            abort(403);
        }

        $nameExists = KitchenStation::query()
            ->where('outlet_id', $outletId)
            ->where('name', $data['name'])
            ->when($station, fn ($query) => $query->whereKeyNot($station->id))
            ->exists();

        if ($nameExists) {
            throw ValidationException::withMessages(['name' => 'Nama station sudah dipakai pada outlet ini.']);
        }

        $categoryConflict = KitchenStationCategory::query()
            ->where('outlet_id', $outletId)
            ->whereIn('category_id', $data['category_ids'] ?? [])
            ->when($station, fn ($query) => $query->where('kitchen_station_id', '!=', $station->id))
            ->exists();

        if ($categoryConflict) {
            throw ValidationException::withMessages(['category_ids' => 'Kategori sudah dipetakan ke station lain pada outlet ini.']);
        }

        $productConflict = ProductKitchenStationOverride::query()
            ->where('outlet_id', $outletId)
            ->whereIn('product_id', $data['product_ids'] ?? [])
            ->when($station, fn ($query) => $query->where('kitchen_station_id', '!=', $station->id))
            ->exists();

        if ($productConflict) {
            throw ValidationException::withMessages(['product_ids' => 'Produk sudah di-override ke station lain pada outlet ini.']);
        }

        return $data;
    }

    private function syncStation(array $data, ?KitchenStation $station): KitchenStation
    {
        return DB::transaction(function () use ($data, $station) {
            $attributes = [
                'outlet_id' => $data['outlet_id'] ?? null,
                'name' => $data['name'],
                'sort_order' => $data['sort_order'] ?? 0,
                'is_active' => $data['is_active'] ?? true,
            ];

            if ($station) {
                $station->update($attributes);
            } else {
                $station = KitchenStation::create($attributes);
            }

            $station->categories()->syncWithPivotValues(
                $data['category_ids'] ?? [],
                ['outlet_id' => $station->outlet_id],
            );
            $station->users()->sync($data['user_ids'] ?? []);

            ProductKitchenStationOverride::where('kitchen_station_id', $station->id)->delete();

            foreach (array_unique($data['product_ids'] ?? []) as $productId) {
                ProductKitchenStationOverride::create([
                    'product_id' => $productId,
                    'kitchen_station_id' => $station->id,
                    'outlet_id' => $station->outlet_id,
                ]);
            }

            return $station->fresh(['categories', 'users', 'productOverrides']);
        });
    }
}
