<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Services\CrmAutomationService;
use App\Services\OutletAccessService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CrmReminderController extends Controller
{
    public function __construct(
        private readonly CrmAutomationService $crmAutomationService,
        private readonly OutletAccessService $outletAccessService
    ) {}

    public function index(Request $request)
    {
        $filters = [
            'type' => $request->input('type'),
            'status' => $request->input('status'),
        ];

        $includeLegacy = Outlet::active()->count() <= 1;
        $outletIds = $request->user()->isSuperAdmin()
            ? null
            : $this->outletAccessService->accessibleOutlets($request->user())->pluck('id')->all();

        $campaigns = $this->crmAutomationService->reminderCampaignsQuery()
            ->when($outletIds !== null, function ($query) use ($outletIds, $includeLegacy) {
                $query->where(function ($query) use ($outletIds, $includeLegacy) {
                    $query->whereIn('outlet_id', $outletIds);
                    if ($includeLegacy) {
                        $query->orWhereNull('outlet_id');
                    }
                });
            })
            ->when($filters['type'], fn ($query, $type) => $query->where('type', $type))
            ->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
            ->paginate($this->perPage())->withQueryString()
            ->withQueryString();

        return Inertia::render('Dashboard/CrmReminders/Index', [
            'campaigns' => $campaigns,
            'filters' => $filters,
        ]);
    }
}
