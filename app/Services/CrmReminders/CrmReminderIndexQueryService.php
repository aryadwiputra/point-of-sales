<?php

declare(strict_types=1);

namespace App\Services\CrmReminders;

use App\Services\CrmAutomationService;

class CrmReminderIndexQueryService
{
    public function __construct(
        private readonly CrmAutomationService $crmAutomationService
    ) {}

    public function execute(array $filters): array
    {
        $campaigns = $this->crmAutomationService->reminderCampaignsQuery()
            ->when($filters['type'], fn ($query, $type) => $query->where('type', $type))
            ->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
            ->paginate(10)
            ->withQueryString();

        return [
            'campaigns' => $campaigns,
            'filters' => $filters,
        ];
    }
}
