<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Apps\CrmReminderController as AppsCrmReminderController;
use App\Http\Traits\ApiResponder;
use App\Models\Outlet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrmReminderController extends AppsCrmReminderController
{
    use ApiResponder;

    /**
     * GET /api/v1/crm-reminders
     */
    public function listReminders(Request $request): JsonResponse
    {
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
            ->when($request->input('type'), fn ($query, $type) => $query->where('type', $type))
            ->when($request->input('status'), fn ($query, $status) => $query->where('status', $status))
            ->paginate($this->perPage())
            ->withQueryString();

        return $this->paginated($campaigns);
    }
}
