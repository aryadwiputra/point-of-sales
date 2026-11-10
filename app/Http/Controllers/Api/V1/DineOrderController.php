<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Apps\DineOrderController as AppsDineOrderController;
use App\Http\Traits\ApiResponder;
use App\Models\DineOrder;
use App\Models\Outlet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DineOrderController extends AppsDineOrderController
{
    use ApiResponder;

    /**
     * GET /api/v1/dine-orders
     */
    public function listOrders(Request $request): JsonResponse
    {
        $includeLegacy = Outlet::active()->count() <= 1;
        $outletIds = $request->user()->isSuperAdmin()
            ? null
            : $this->outletAccessService->accessibleOutlets($request->user())->pluck('id')->all();

        $orders = DineOrder::with(['table.area', 'items.product'])
            ->whereIn('status', [DineOrder::STATUS_SUBMITTED, DineOrder::STATUS_ACCEPTED])
            ->when($outletIds !== null, function ($query) use ($outletIds, $includeLegacy) {
                $query->where(function ($query) use ($outletIds, $includeLegacy) {
                    $query->whereHas('table.area', fn ($area) => $area->whereIn('outlet_id', $outletIds));
                    if ($includeLegacy) {
                        $query->orWhereHas('table.area', fn ($area) => $area->whereNull('outlet_id'));
                    }
                });
            })
            ->latest()
            ->get();

        return $this->ok(['orders' => $orders]);
    }

    /**
     * POST /api/v1/dine-orders/{dineOrder}/accept
     */
    public function acceptOrder(Request $request, DineOrder $dineOrder): JsonResponse
    {
        $this->ensureOutletAccess($request, $dineOrder);
        $this->service->accept($dineOrder);

        return $this->ok($dineOrder->fresh(['table.area', 'items.product']));
    }

    /**
     * POST /api/v1/dine-orders/{dineOrder}/reject
     */
    public function rejectOrder(Request $request, DineOrder $dineOrder): JsonResponse
    {
        $this->ensureOutletAccess($request, $dineOrder);
        $this->service->reject($dineOrder, $request->input('reason'));

        return $this->ok($dineOrder->fresh(['table.area', 'items.product']));
    }
}
