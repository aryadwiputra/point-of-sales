<?php

declare(strict_types=1);

namespace App\Services\CrmCampaigns;

use App\Models\CustomerCampaign;

class CrmCampaignFormQueryService
{
    public function __construct(
        private readonly CrmCampaignAudienceService $audienceService
    ) {}

    public function execute(?CustomerCampaign $campaign = null): array
    {
        return [
            'campaign' => $campaign,
            'audienceOptions' => $this->audienceService->options(),
        ];
    }
}
