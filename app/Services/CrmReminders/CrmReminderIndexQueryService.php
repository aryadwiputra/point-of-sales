<?php

declare(strict_types=1);

namespace App\Services\CrmReminders;

use App\Models\CustomerCampaign;

class CrmReminderIndexQueryService
{
    public function execute(array $filters): array
    {
        $campaigns = CustomerCampaign::query()
            ->with(['creator:id,name', 'logs.customer:id,name,no_telp', 'logs.transaction:id,invoice', 'logs.receivable:id,invoice,due_date'])
            ->whereIn('type', [
                CustomerCampaign::TYPE_DUE_DATE_REMINDER,
                CustomerCampaign::TYPE_REPEAT_ORDER_REMINDER,
                CustomerCampaign::TYPE_PROMO_BROADCAST,
                CustomerCampaign::TYPE_INVOICE_SHARE,
            ])
            ->when($filters['type'], fn ($query, $type) => $query->where('type', $type))
            ->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('processed_at')
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        return [
            'campaigns' => $campaigns,
            'filters' => $filters,
        ];
    }
}
