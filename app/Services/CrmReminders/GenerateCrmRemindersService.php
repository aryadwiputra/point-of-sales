<?php

declare(strict_types=1);

namespace App\Services\CrmReminders;

use App\Models\Customer;
use App\Models\CustomerCampaign;
use App\Models\CustomerCampaignLog;
use App\Models\Receivable;
use App\Services\CustomerSegmentationService;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GenerateCrmRemindersService
{
    public function __construct(
        private readonly CustomerSegmentationService $segmentationService
    ) {}

    public function execute(?CarbonInterface $at = null): void
    {
        $at = $at ?? now();
        $this->segmentationService->syncAutoSegments($at);

        DB::transaction(fn () => $this->generateDueSoonCampaign($at));
        DB::transaction(fn () => $this->generateOverdueCampaign($at));
        DB::transaction(fn () => $this->generateRepeatOrderCampaign($at));
    }

    private function generateDueSoonCampaign(CarbonInterface $at): void
    {
        $campaign = CustomerCampaign::query()->firstOrCreate(
            ['context_key' => 'due-soon-'.$at->toDateString()],
            [
                'name' => 'Reminder Jatuh Tempo H-3 '.$at->format('d M Y'),
                'type' => CustomerCampaign::TYPE_DUE_DATE_REMINDER,
                'status' => CustomerCampaign::STATUS_READY,
                'channel' => CustomerCampaign::CHANNEL_INTERNAL,
                'audience_filters' => ['receivable_status' => 'due_soon'],
                'message_template' => 'Pengingat: invoice {{invoice}} jatuh tempo {{due_date}}',
                'processed_at' => $at,
            ]
        );

        if ($campaign->wasRecentlyCreated) {
            $receivables = Receivable::query()
                ->with('customer:id,name,no_telp')
                ->where('status', '!=', 'paid')
                ->whereBetween('due_date', [$at->copy()->startOfDay(), $at->copy()->addDays(3)->endOfDay()])
                ->get();

            $this->fillReceivableReminderLogs($campaign, $receivables, 'jatuh tempo');
        }
    }

    private function generateOverdueCampaign(CarbonInterface $at): void
    {
        $campaign = CustomerCampaign::query()->firstOrCreate(
            ['context_key' => 'overdue-'.$at->toDateString()],
            [
                'name' => 'Reminder Piutang Overdue '.$at->format('d M Y'),
                'type' => CustomerCampaign::TYPE_DUE_DATE_REMINDER,
                'status' => CustomerCampaign::STATUS_READY,
                'channel' => CustomerCampaign::CHANNEL_INTERNAL,
                'audience_filters' => ['receivable_status' => 'overdue'],
                'message_template' => 'Piutang {{invoice}} telah overdue sejak {{due_date}}',
                'processed_at' => $at,
            ]
        );

        if ($campaign->wasRecentlyCreated) {
            $receivables = Receivable::query()
                ->with('customer:id,name,no_telp')
                ->where('status', '!=', 'paid')
                ->whereDate('due_date', '<', $at->toDateString())
                ->get();

            $this->fillReceivableReminderLogs($campaign, $receivables, 'overdue');
        }
    }

    private function generateRepeatOrderCampaign(CarbonInterface $at): void
    {
        $campaign = CustomerCampaign::query()->firstOrCreate(
            ['context_key' => 'repeat-order-'.$at->toDateString()],
            [
                'name' => 'Repeat Order Reminder '.$at->format('d M Y'),
                'type' => CustomerCampaign::TYPE_REPEAT_ORDER_REMINDER,
                'status' => CustomerCampaign::STATUS_READY,
                'channel' => CustomerCampaign::CHANNEL_INTERNAL,
                'audience_filters' => ['segment_slugs' => ['inactive_customer']],
                'message_template' => 'Sudah lama tidak belanja. Ajak customer kembali bertransaksi.',
                'processed_at' => $at,
            ]
        );

        if (! $campaign->wasRecentlyCreated) {
            return;
        }

        $customers = Customer::query()
            ->with('segments')
            ->where('loyalty_transaction_count', '>', 0)
            ->where(function ($query) use ($at) {
                $query->whereNull('last_purchase_at')
                    ->orWhere('last_purchase_at', '<', $at->copy()->subDays(30));
            })
            ->get();

        foreach ($customers as $customer) {
            $message = 'Halo '.$customer->name.', kami merindukan kunjungan Anda. Yuk belanja lagi hari ini.';

            $campaign->logs()->create([
                'customer_id' => $customer->id,
                'channel' => CustomerCampaign::CHANNEL_WHATSAPP_LINK,
                'status' => CustomerCampaignLog::STATUS_READY_TO_SEND,
                'payload' => [
                    'message' => $message,
                    'whatsapp_url' => 'https://wa.me/?text='.urlencode($message),
                    'reason' => 'inactive_customer',
                ],
            ]);
        }

        $campaign->update([
            'audience_snapshot' => $customers->map(fn (Customer $customer) => [
                'customer_id' => $customer->id,
                'name' => $customer->name,
                'reason' => 'inactive_customer',
            ])->values()->all(),
        ]);
    }

    private function fillReceivableReminderLogs(CustomerCampaign $campaign, Collection $receivables, string $reason): void
    {
        foreach ($receivables as $receivable) {
            $message = sprintf(
                'Pengingat %s untuk invoice %s. Sisa tagihan Rp %s. Jatuh tempo %s.',
                $reason,
                $receivable->invoice,
                number_format($receivable->remaining, 0, ',', '.'),
                optional($receivable->due_date)?->format('d/m/Y') ?? '-'
            );

            $campaign->logs()->create([
                'customer_id' => $receivable->customer_id,
                'receivable_id' => $receivable->id,
                'channel' => CustomerCampaign::CHANNEL_WHATSAPP_LINK,
                'status' => CustomerCampaignLog::STATUS_READY_TO_SEND,
                'payload' => [
                    'message' => $message,
                    'whatsapp_url' => 'https://wa.me/?text='.urlencode($message),
                    'reason' => $reason,
                    'invoice' => $receivable->invoice,
                ],
            ]);
        }

        $campaign->update([
            'audience_snapshot' => $receivables->map(fn (Receivable $receivable) => [
                'customer_id' => $receivable->customer_id,
                'receivable_id' => $receivable->id,
                'invoice' => $receivable->invoice,
                'due_date' => optional($receivable->due_date)?->toDateString(),
                'remaining' => $receivable->remaining,
            ])->values()->all(),
        ]);
    }
}
