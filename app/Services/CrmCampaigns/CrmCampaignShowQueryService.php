<?php

declare(strict_types=1);

namespace App\Services\CrmCampaigns;

use App\Models\CustomerCampaign;

class CrmCampaignShowQueryService
{
    public function execute(CustomerCampaign $campaign): array
    {
        return [
            'campaign' => $campaign->load([
                'creator:id,name',
                'logs.customer:id,name,no_telp',
                'logs.transaction:id,invoice',
                'logs.receivable:id,invoice,due_date',
            ]),
        ];
    }
}
