<?php

declare(strict_types=1);

namespace App\Services\CrmCampaigns;

use App\Models\Customer;
use App\Models\CustomerCampaign;
use App\Services\CustomerSegmentationService;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class CrmCampaignAudienceService
{
    public function __construct(
        private readonly CustomerSegmentationService $segmentationService
    ) {}

    public function options(): array
    {
        return [
            'customer_types' => [
                ['value' => 'all', 'label' => 'Semua Customer'],
                ['value' => 'member', 'label' => 'Loyalty Member'],
                ['value' => 'non_member', 'label' => 'Non Member'],
            ],
            'receivable_statuses' => [
                ['value' => 'all', 'label' => 'Semua Status Piutang'],
                ['value' => 'has_receivable', 'label' => 'Punya Piutang'],
                ['value' => 'overdue', 'label' => 'Piutang Overdue'],
                ['value' => 'due_soon', 'label' => 'Jatuh Tempo H-3'],
            ],
            'voucher_filters' => [
                ['value' => 'all', 'label' => 'Semua Customer'],
                ['value' => 'has_active_voucher', 'label' => 'Punya Voucher Aktif'],
                ['value' => 'no_active_voucher', 'label' => 'Tidak Punya Voucher Aktif'],
            ],
            'segment_options' => $this->segmentationService->segmentOptions(),
        ];
    }

    public function build(array $filters, ?CarbonInterface $at = null): Collection
    {
        $at = $at ?? now();
        $query = Customer::query()
            ->with(['segments', 'receivables', 'vouchers'])
            ->orderBy('name');

        $segmentIds = collect($filters['segment_ids'] ?? [])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($segmentIds !== []) {
            $query->whereHas('segments', fn ($builder) => $builder->whereIn('customer_segments.id', $segmentIds));
        }

        $customerType = $filters['customer_type'] ?? 'all';
        if ($customerType === 'member') {
            $query->where('is_loyalty_member', true);
        } elseif ($customerType === 'non_member') {
            $query->where('is_loyalty_member', false);
        }

        return $query->get()
            ->filter(fn (Customer $customer) => $this->matchesFilters($customer, $filters, $at))
            ->values();
    }

    public function customerPayload(CustomerCampaign $campaign, Customer $customer): array
    {
        $template = $campaign->message_template ?: 'Halo {{name}}, ada promo spesial untuk Anda.';
        $message = str_replace(
            ['{{name}}', '{{phone}}'],
            [$customer->name, $customer->no_telp],
            $template
        );

        return [
            'message' => $message,
            'whatsapp_url' => 'https://wa.me/?text='.urlencode($message),
            'segments' => $customer->segments->pluck('slug')->values()->all(),
        ];
    }

    private function matchesFilters(Customer $customer, array $filters, CarbonInterface $at): bool
    {
        $hasActiveVoucher = $customer->vouchers
            ->filter(fn ($voucher) => $voucher->currentStatusLabel() === 'active')
            ->isNotEmpty();
        $voucherFilter = $filters['voucher_filter'] ?? 'all';

        if ($voucherFilter === 'has_active_voucher' && ! $hasActiveVoucher) {
            return false;
        }

        if ($voucherFilter === 'no_active_voucher' && $hasActiveVoucher) {
            return false;
        }

        $hasOutstanding = $customer->receivables
            ->filter(fn ($receivable) => $receivable->status !== 'paid' && $receivable->remaining > 0)
            ->isNotEmpty();
        $hasOverdue = $customer->receivables
            ->filter(fn ($receivable) => $receivable->status !== 'paid' && $receivable->due_date && $at->gt($receivable->due_date))
            ->isNotEmpty();
        $hasDueSoon = $customer->receivables
            ->filter(function ($receivable) use ($at) {
                if ($receivable->status === 'paid' || ! $receivable->due_date) {
                    return false;
                }

                return $receivable->due_date->between($at->copy()->startOfDay(), $at->copy()->addDays(3)->endOfDay());
            })
            ->isNotEmpty();

        return match ($filters['receivable_status'] ?? 'all') {
            'has_receivable' => $hasOutstanding,
            'overdue' => $hasOverdue,
            'due_soon' => $hasDueSoon,
            default => true,
        };
    }
}
