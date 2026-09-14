<?php

namespace App\Services;

use App\Models\Outlet;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Collection;

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

    public function warehousesFor(User $user): Collection
    {
        $query = Warehouse::query()->active()->orderBy('sort_order')->orderBy('code');
        if (! $user->isSuperAdmin()) {
            $outletIds = $user->outlets()->pluck('outlets.id');
            if ($outletIds->isNotEmpty()) {
                $query->whereIn('outlet_id', $outletIds);
            }
        }

        return $query->get(['id', 'code', 'name']);
    }
}
