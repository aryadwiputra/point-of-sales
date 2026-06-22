<?php

declare(strict_types=1);

namespace App\Services\CrmCampaigns;

use App\Models\Customer;
use App\Models\CustomerCampaign;
use App\Models\CustomerCampaignLog;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class CrmCampaignLifecycleService
{
    public function __construct(
        private readonly CrmCampaignAudienceService $audienceService
    ) {}

    public function create(array $data, int $userId, bool $saveAsDraft = true): CustomerCampaign
    {
        return DB::transaction(function () use ($data, $userId, $saveAsDraft) {
            $campaign = CustomerCampaign::query()->create([
                'name' => $data['name'],
                'type' => $data['type'],
                'status' => CustomerCampaign::STATUS_DRAFT,
                'channel' => $data['channel'] ?? CustomerCampaign::CHANNEL_INTERNAL,
                'audience_filters' => $data['audience_filters'] ?? [],
                'message_template' => $data['message_template'] ?? null,
                'created_by' => $userId,
            ]);

            return $saveAsDraft ? $campaign : $this->process($campaign);
        });
    }

    public function update(CustomerCampaign $campaign, array $data): CustomerCampaign
    {
        $campaign->update([
            'name' => $data['name'],
            'channel' => $data['channel'] ?? CustomerCampaign::CHANNEL_INTERNAL,
            'audience_filters' => $data['audience_filters'] ?? [],
            'message_template' => $data['message_template'] ?? null,
        ]);

        return $campaign->fresh();
    }

    public function delete(CustomerCampaign $campaign): void
    {
        $campaign->delete();
    }

    public function process(CustomerCampaign $campaign, ?CarbonInterface $at = null): CustomerCampaign
    {
        return DB::transaction(function () use ($campaign, $at) {
            $at = $at ?? now();
            $campaign->logs()->delete();
            $audience = $this->audienceService->build($campaign->audience_filters ?? [], $at);
            $snapshot = $audience->map(fn (Customer $customer) => [
                'customer_id' => $customer->id,
                'name' => $customer->name,
                'no_telp' => $customer->no_telp,
                'is_loyalty_member' => (bool) $customer->is_loyalty_member,
                'segments' => $customer->segments->pluck('name')->values()->all(),
            ])->values()->all();

            foreach ($audience as $customer) {
                $campaign->logs()->create([
                    'customer_id' => $customer->id,
                    'channel' => CustomerCampaign::CHANNEL_WHATSAPP_LINK,
                    'status' => CustomerCampaignLog::STATUS_READY_TO_SEND,
                    'payload' => $this->audienceService->customerPayload($campaign, $customer),
                ]);
            }

            $campaign->update([
                'status' => CustomerCampaign::STATUS_READY,
                'audience_snapshot' => $snapshot,
                'processed_at' => $at,
            ]);

            return $campaign->fresh(['logs.customer']);
        });
    }

    public function cancel(CustomerCampaign $campaign): CustomerCampaign
    {
        $campaign->update(['status' => CustomerCampaign::STATUS_CANCELLED]);

        return $campaign->fresh();
    }

    public function markLog(CustomerCampaignLog $log, string $status): CustomerCampaignLog
    {
        return DB::transaction(function () use ($log, $status) {
            $payload = ['status' => $status];

            if ($status === CustomerCampaignLog::STATUS_SENT) {
                $payload['sent_at'] = now();
            }

            $log->update($payload);
            $this->refreshStatus($log->campaign);

            return $log->fresh();
        });
    }

    private function refreshStatus(?CustomerCampaign $campaign): void
    {
        if (! $campaign) {
            return;
        }

        $hasPending = $campaign->logs()
            ->whereIn('status', [
                CustomerCampaignLog::STATUS_PENDING,
                CustomerCampaignLog::STATUS_READY_TO_SEND,
            ])
            ->exists();

        $campaign->update([
            'status' => $hasPending
                ? CustomerCampaign::STATUS_READY
                : CustomerCampaign::STATUS_PROCESSED,
        ]);
    }
}
