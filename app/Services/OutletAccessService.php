<?php

namespace App\Services;

use App\Models\CashierShift;
use App\Models\Outlet;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OutletAccessService
{
    public function canUseWarehouse(User $user, ?Warehouse $warehouse): bool
    {
        if (! $warehouse) {
            // Legacy installs allowed shifts without a warehouse assignment.
            return Outlet::active()->count() <= 1;
        }

        if (! $warehouse->is_active) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        $outletIds = $user->outlets()->pluck('outlets.id');

        // Backward compatibility: old single-outlet installs have no assignments yet.
        if ($outletIds->isEmpty()) {
            return Outlet::active()->count() <= 1;
        }

        return (bool) ($warehouse->outlet_id && $outletIds->contains($warehouse->outlet_id));
    }

    public function canSellAtWarehouse(User $user, ?Warehouse $warehouse): bool
    {
        return $this->canUseWarehouse($user, $warehouse)
            && (! $warehouse?->outlet_id && Outlet::active()->count() <= 1
                || (bool) ($warehouse?->outlet ?? Outlet::find($warehouse?->outlet_id))?->is_sales_enabled);
    }

    public function salesWarehousesFor(User $user): Collection
    {
        return $this->warehousesFor($user)
            ->filter(fn (Warehouse $warehouse) => ! $warehouse->outlet_id
                ? Outlet::active()->count() <= 1
                : (bool) $warehouse->outlet?->is_sales_enabled)
            ->values();
    }

    public function warehousesFor(User $user): Collection
    {
        $query = Warehouse::query()->active()->orderBy('sort_order')->orderBy('code');
        if (! $user->isSuperAdmin()) {
            $outletIds = $user->outlets()->pluck('outlets.id');
            if ($outletIds->isNotEmpty()) {
                $query->whereIn('outlet_id', $outletIds);
            }
        }

        return $query->with('outlet:id,is_sales_enabled')->get(['id', 'code', 'name', 'outlet_id']);
    }

    public function defaultOutlet(User $user): ?Outlet
    {
        if ($user->isSuperAdmin()) {
            return Outlet::active()->orderBy('code')->first();
        }

        return $user->outlets()
            ->where('outlets.is_active', true)
            ->orderByDesc('user_outlets.is_default')
            ->orderBy('outlets.code')
            ->first();
    }

    public function accessibleOutlets(User $user): Collection
    {
        if ($user->isSuperAdmin()) {
            return Outlet::active()->orderBy('code')->get();
        }

        return $user->outlets()
            ->where('outlets.is_active', true)
            ->orderByDesc('user_outlets.is_default')
            ->orderBy('outlets.code')
            ->get();
    }

    public function activeOutlet(Request $request): ?Outlet
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }

        $shiftOutlet = CashierShift::query()
            ->with('warehouse.outlet')
            ->open()
            ->where('user_id', $user->id)
            ->latest('opened_at')
            ->first()?->warehouse?->outlet;

        if ($shiftOutlet) {
            return $shiftOutlet;
        }

        $outlets = $this->accessibleOutlets($user);
        $selectedId = (int) $request->session()->get('active_outlet_id');

        return $outlets->firstWhere('id', $selectedId) ?? $outlets->first() ?? $this->defaultOutlet($user);
    }

    public function hasActiveShiftInOtherOutlet(User $user, Outlet $outlet): bool
    {
        return (bool) CashierShift::query()
            ->where('user_id', $user->id)
            ->open()
            ->whereHas('warehouse', fn ($query) => $query->where('outlet_id', '!=', $outlet->id))
            ->exists();
    }

    public function hasWarehouseHistory(Warehouse $warehouse): bool
    {
        $warehouseId = $warehouse->id;

        foreach ([
            'transactions',
            'carts',
            'stock_mutations',
            'cashier_shifts',
            'purchase_orders',
            'goods_receivings',
            'supplier_returns',
            'stock_opnames',
            'product_batches',
        ] as $table) {
            if (Schema::hasTable($table)
                && Schema::hasColumn($table, 'warehouse_id')
                && DB::table($table)->where('warehouse_id', $warehouseId)->exists()) {
                return true;
            }
        }

        return Schema::hasTable('stock_transfers')
            && (DB::table('stock_transfers')->where('source_warehouse_id', $warehouseId)->exists()
                || DB::table('stock_transfers')->where('destination_warehouse_id', $warehouseId)->exists());
    }

    public function hasOpenShift(Warehouse $warehouse): bool
    {
        return Schema::hasTable('cashier_shifts')
            && DB::table('cashier_shifts')
                ->where('warehouse_id', $warehouse->id)
                ->where('status', 'open')
                ->exists();
    }
}
