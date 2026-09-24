<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Models\DineOrder;
use App\Models\Outlet;
use App\Services\DineOrderService;
use App\Services\OutletAccessService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DineOrderController extends Controller
{
    public function __construct(
        private DineOrderService $service,
        private readonly OutletAccessService $outletAccessService,
    ) {}

    public function index(Request $request)
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

        return Inertia::render('Dashboard/DineIn/Orders/Index', [
            'orders' => $orders,
        ]);
    }

    public function accept(Request $request, DineOrder $dineOrder)
    {
        $this->ensureOutletAccess($request, $dineOrder);
        $this->service->accept($dineOrder);

        return back()->with('success', 'Pesanan diterima dan siap diproses di kasir.');
    }

    public function reject(Request $request, DineOrder $dineOrder)
    {
        $this->ensureOutletAccess($request, $dineOrder);
        $this->service->reject($dineOrder, $request->input('reason'));

        return back()->with('success', 'Pesanan ditolak.');
    }

    private function ensureOutletAccess(Request $request, DineOrder $dineOrder): void
    {
        $user = $request->user();
        if ($user->isSuperAdmin()) {
            return;
        }

        $accessibleOutletIds = $this->outletAccessService->accessibleOutlets($user)->pluck('id');

        // Legacy / unassigned users: defer to the service-level shift-vs-table outlet guard.
        if ($accessibleOutletIds->isEmpty()) {
            return;
        }

        $outletId = $dineOrder->table?->area?->outlet_id;

        if ($outletId === null) {
            abort_unless(Outlet::active()->count() <= 1, 404);

            return;
        }

        abort_unless($accessibleOutletIds->contains((int) $outletId), 404);
    }
}
