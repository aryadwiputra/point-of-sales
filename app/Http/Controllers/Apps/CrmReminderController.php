<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Services\CrmAutomationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CrmReminderController extends Controller
{
    public function __construct(
        private readonly CrmAutomationService $crmAutomationService
    ) {}

    public function index(Request $request)
    {
        $filters = [
            'type' => $request->input('type'),
            'status' => $request->input('status'),
        ];

        $campaigns = $this->crmAutomationService->reminderCampaignsQuery()
            ->when($filters['type'], fn ($query, $type) => $query->where('type', $type))
            ->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
            ->paginate(10)->withQueryString()
            ->withQueryString();

        return Inertia::render('Dashboard/CrmReminders/Index', [
            'campaigns' => $campaigns,
            'filters' => $filters,
        ]);
    }
}
