<?php

declare(strict_types=1);

namespace App\Services\CrmCampaigns;

use App\Models\CustomerCampaign;

class CrmCampaignIndexQueryService
{
    public function execute(array $filters): array
    {
        return [
            'campaigns' => CustomerCampaign::query()
                ->with(['creator:id,name'])
                ->withCount('logs')
                ->when($filters['type'], fn ($query, $type) => $query->where('type', $type))
                ->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
                ->orderByDesc('created_at')
                ->paginate(10)
                ->withQueryString(),
            'filters' => $filters,
        ];
    }
}
