<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOutletRequest;
use App\Models\Outlet;
use App\Models\Product;
use App\Services\OutletAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OutletController extends Controller
{
    public function __construct(private readonly OutletAccessService $outletAccessService) {}

    public function index(): Response
    {
        $outlets = $this->outletAccessService->accessibleOutlets(request()->user())
            ->load(['warehouses' => fn ($query) => $query->orderBy('sort_order')->orderBy('code')]);

        return Inertia::render('Dashboard/Settings/Outlets/Index', ['outlets' => $outlets]);
    }

    public function store(StoreOutletRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($request, $data): void {
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
        });

        return to_route('settings.outlets.index')->with('success', 'Outlet berhasil dibuat.');
    }
}
