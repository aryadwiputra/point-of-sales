<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\CustomerCampaign;
use App\Models\CustomerCampaignLog;
use App\Models\CustomerSegment;
use App\Models\CustomerVoucher;
use App\Models\LoyaltyPointHistory;
use App\Models\PricingRule;
use App\Repositories\Reports\AdvancedSalesInsightsRepository;

class OperationalInsightsQueryService
{
    public function __construct(
        private readonly AdvancedSalesInsightsRepository $repository
    ) {}

    public function execute(array $filters): array
    {
        return [
            'promoMonitor' => $this->promoMonitor(),
            'loyaltyPerformance' => $this->loyaltyPerformance($filters),
            'crmOperations' => $this->crmOperations($filters),
        ];
    }

    private function promoMonitor(): array
    {
        $rules = PricingRule::query()
            ->with(['product:id,title', 'category:id,name'])
            ->orderByDesc('priority')
            ->orderBy('name')
            ->get();

        return [
            'summary' => [
                'active' => $rules->filter(fn (PricingRule $rule) => $rule->currentStatusLabel() === 'active')->count(),
                'scheduled' => $rules->filter(fn (PricingRule $rule) => $rule->currentStatusLabel() === 'scheduled')->count(),
                'expired' => $rules->filter(fn (PricingRule $rule) => $rule->currentStatusLabel() === 'expired')->count(),
                'inactive' => $rules->filter(fn (PricingRule $rule) => $rule->currentStatusLabel() === 'inactive')->count(),
                'by_kind' => [
                    PricingRule::KIND_STANDARD_DISCOUNT => $rules->where('kind', PricingRule::KIND_STANDARD_DISCOUNT)->count(),
                    PricingRule::KIND_QTY_BREAK => $rules->where('kind', PricingRule::KIND_QTY_BREAK)->count(),
                    PricingRule::KIND_BUNDLE_PRICE => $rules->where('kind', PricingRule::KIND_BUNDLE_PRICE)->count(),
                    PricingRule::KIND_BUY_X_GET_Y => $rules->where('kind', PricingRule::KIND_BUY_X_GET_Y)->count(),
                ],
            ],
            'active_rules' => $rules
                ->filter(fn (PricingRule $rule) => $rule->currentStatusLabel() === 'active')
                ->take(5)
                ->values()
                ->map(fn (PricingRule $rule) => $this->serializePromoRule($rule))
                ->all(),
            'scheduled_rules' => $rules
                ->filter(fn (PricingRule $rule) => $rule->currentStatusLabel() === 'scheduled')
                ->sortBy(fn (PricingRule $rule) => optional($rule->starts_at)?->timestamp ?? PHP_INT_MAX)
                ->take(5)
                ->values()
                ->map(fn (PricingRule $rule) => $this->serializePromoRule($rule))
                ->all(),
            'recent_audits' => AuditLog::query()
                ->where('module', 'pricing_rules')
                ->latest('id')
                ->limit(5)
                ->get()
                ->map(fn (AuditLog $log) => [
                    'id' => $log->id,
                    'event' => $log->event,
                    'description' => $log->description,
                    'created_at' => optional($log->created_at)?->toIso8601String(),
                ])
                ->all(),
        ];
    }

    private function loyaltyPerformance(array $filters): array
    {
        $members = Customer::query()
            ->where('is_loyalty_member', true)
            ->get();

        $historyQuery = LoyaltyPointHistory::query();
        $this->repository->applyDateRangeFilter($historyQuery, 'created_at', $filters);

        $vouchers = CustomerVoucher::query()->get();

        return [
            'summary' => [
                'total_members' => $members->count(),
                'points_balance_total' => (int) $members->sum('loyalty_points'),
                'points_earned' => (int) (clone $historyQuery)
                    ->where('type', LoyaltyPointHistory::TYPE_EARN)
                    ->sum('points_delta'),
                'points_redeemed' => (int) abs((int) (clone $historyQuery)
                    ->where('type', LoyaltyPointHistory::TYPE_REDEEM)
                    ->sum('points_delta')),
                'voucher_discount_total' => (int) (clone $historyQuery)
                    ->where('type', LoyaltyPointHistory::TYPE_VOUCHER)
                    ->sum('amount_delta'),
                'tier_distribution' => [
                    'regular' => $members->where('loyalty_tier', 'regular')->count(),
                    'silver' => $members->where('loyalty_tier', 'silver')->count(),
                    'gold' => $members->where('loyalty_tier', 'gold')->count(),
                    'platinum' => $members->where('loyalty_tier', 'platinum')->count(),
                ],
                'voucher_summary' => [
                    'active' => $vouchers->filter(fn (CustomerVoucher $voucher) => $voucher->currentStatusLabel() === 'active')->count(),
                    'scheduled' => $vouchers->filter(fn (CustomerVoucher $voucher) => $voucher->currentStatusLabel() === 'scheduled')->count(),
                    'expired' => $vouchers->filter(fn (CustomerVoucher $voucher) => $voucher->currentStatusLabel() === 'expired')->count(),
                    'used' => $vouchers->filter(fn (CustomerVoucher $voucher) => $voucher->currentStatusLabel() === 'used')->count(),
                    'inactive' => $vouchers->filter(fn (CustomerVoucher $voucher) => $voucher->currentStatusLabel() === 'inactive')->count(),
                ],
            ],
            'top_members' => Customer::query()
                ->where('is_loyalty_member', true)
                ->orderByDesc('loyalty_total_spent')
                ->orderByDesc('loyalty_points')
                ->limit(5)
                ->get()
                ->map(fn (Customer $customer) => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'loyalty_tier' => $customer->loyalty_tier,
                    'loyalty_points' => (int) $customer->loyalty_points,
                    'loyalty_total_spent' => (int) $customer->loyalty_total_spent,
                    'loyalty_transaction_count' => (int) $customer->loyalty_transaction_count,
                ])
                ->all(),
        ];
    }

    private function crmOperations(array $filters): array
    {
        $segments = CustomerSegment::query()->withCount('memberships')->get();

        $campaignsQuery = CustomerCampaign::query()->withCount('logs');
        $this->repository->applyDateRangeFilter($campaignsQuery, 'created_at', $filters);
        $campaigns = $campaignsQuery->get();

        $logsQuery = CustomerCampaignLog::query();
        $this->repository->applyDateRangeFilter($logsQuery, 'created_at', $filters);
        $logs = $logsQuery->get();

        return [
            'summary' => [
                'segments_total' => $segments->count(),
                'segments_manual' => $segments->where('type', CustomerSegment::TYPE_MANUAL)->count(),
                'segments_auto' => $segments->where('type', CustomerSegment::TYPE_AUTO)->count(),
                'segments_active' => $segments->where('is_active', true)->count(),
                'memberships_total' => (int) $segments->sum('memberships_count'),
                'campaigns_total' => $campaigns->count(),
                'campaigns_draft' => $campaigns->where('status', CustomerCampaign::STATUS_DRAFT)->count(),
                'campaigns_ready' => $campaigns->where('status', CustomerCampaign::STATUS_READY)->count(),
                'campaigns_processed' => $campaigns->where('status', CustomerCampaign::STATUS_PROCESSED)->count(),
                'campaigns_cancelled' => $campaigns->where('status', CustomerCampaign::STATUS_CANCELLED)->count(),
                'queue_pending' => $logs->where('status', CustomerCampaignLog::STATUS_PENDING)->count(),
                'queue_ready_to_send' => $logs->where('status', CustomerCampaignLog::STATUS_READY_TO_SEND)->count(),
                'queue_sent' => $logs->where('status', CustomerCampaignLog::STATUS_SENT)->count(),
                'queue_skipped' => $logs->where('status', CustomerCampaignLog::STATUS_SKIPPED)->count(),
            ],
            'recent_campaigns' => CustomerCampaign::query()
                ->withCount('logs')
                ->latest('id')
                ->limit(5)
                ->get()
                ->map(fn (CustomerCampaign $campaign) => [
                    'id' => $campaign->id,
                    'name' => $campaign->name,
                    'type' => $campaign->type,
                    'status' => $campaign->status,
                    'channel' => $campaign->channel,
                    'logs_count' => (int) $campaign->logs_count,
                    'processed_at' => optional($campaign->processed_at)?->toIso8601String(),
                    'created_at' => optional($campaign->created_at)?->toIso8601String(),
                ])
                ->all(),
        ];
    }

    private function serializePromoRule(PricingRule $rule): array
    {
        return [
            'id' => $rule->id,
            'name' => $rule->name,
            'kind' => $rule->kind,
            'status_label' => $rule->currentStatusLabel(),
            'priority' => (int) $rule->priority,
            'target_type' => $rule->target_type,
            'customer_scope' => $rule->customer_scope,
            'product_title' => $rule->product?->title,
            'category_name' => $rule->category?->name,
            'starts_at' => optional($rule->starts_at)?->toIso8601String(),
            'ends_at' => optional($rule->ends_at)?->toIso8601String(),
        ];
    }
}
