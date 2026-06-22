<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerSegment;
use App\Models\CustomerSegmentMembership;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class CustomerSegmentationService
{
    public function defaultAutoSegments(): array
    {
        return [
            [
                'name' => 'High Spender',
                'slug' => 'high_spender',
                'type' => CustomerSegment::TYPE_AUTO,
                'description' => 'Pelanggan dengan total belanja tinggi.',
                'auto_rule_type' => CustomerSegment::RULE_SPENDING,
                'rule_config' => [
                    'min_total_spent' => 1500000,
                ],
            ],
            [
                'name' => 'Frequent Buyer',
                'slug' => 'frequent_buyer',
                'type' => CustomerSegment::TYPE_AUTO,
                'description' => 'Pelanggan yang aktif berbelanja dengan frekuensi tinggi.',
                'auto_rule_type' => CustomerSegment::RULE_PURCHASE_FREQUENCY,
                'rule_config' => [
                    'min_transaction_count' => 5,
                    'recent_days' => 45,
                ],
            ],
            [
                'name' => 'Inactive Customer',
                'slug' => 'inactive_customer',
                'type' => CustomerSegment::TYPE_AUTO,
                'description' => 'Pelanggan yang sudah lama tidak melakukan pembelian ulang.',
                'auto_rule_type' => CustomerSegment::RULE_PURCHASE_FREQUENCY,
                'rule_config' => [
                    'inactivity_days_min' => 30,
                    'min_transaction_count' => 1,
                ],
            ],
            [
                'name' => 'Credit Customer',
                'slug' => 'credit_customer',
                'type' => CustomerSegment::TYPE_AUTO,
                'description' => 'Pelanggan yang masih memiliki piutang aktif.',
                'auto_rule_type' => CustomerSegment::RULE_RECEIVABLE_BEHAVIOR,
                'rule_config' => [
                    'require_outstanding_receivable' => true,
                ],
            ],
            [
                'name' => 'Overdue Customer',
                'slug' => 'overdue_customer',
                'type' => CustomerSegment::TYPE_AUTO,
                'description' => 'Pelanggan dengan piutang jatuh tempo atau overdue.',
                'auto_rule_type' => CustomerSegment::RULE_RECEIVABLE_BEHAVIOR,
                'rule_config' => [
                    'overdue_only' => true,
                ],
            ],
        ];
    }

    public function ensureDefaultAutoSegments(): void
    {
        DB::transaction(function () {
            foreach ($this->defaultAutoSegments() as $segment) {
                CustomerSegment::query()->updateOrCreate(
                    ['slug' => $segment['slug']],
                    [
                        ...$segment,
                        'is_active' => true,
                    ]
                );
            }
        });
    }

    public function syncAutoSegments(?CarbonInterface $at = null): void
    {
        $at = $at ?? now();
        $this->ensureDefaultAutoSegments();

        DB::transaction(function () use ($at) {
            $segments = CustomerSegment::query()
                ->where('type', CustomerSegment::TYPE_AUTO)
                ->where('is_active', true)
                ->get();

            Customer::query()
                ->with(['receivables'])
                ->orderBy('id')
                ->chunkById(100, function ($customers) use ($segments, $at) {
                    foreach ($customers as $customer) {
                        foreach ($segments as $segment) {
                            $matches = $this->matchesAutoSegment($customer, $segment, $at);

                            if ($matches) {
                                CustomerSegmentMembership::query()->updateOrCreate([
                                    'customer_id' => $customer->id,
                                    'customer_segment_id' => $segment->id,
                                ], [
                                    'source' => CustomerSegmentMembership::SOURCE_AUTO,
                                    'matched_at' => $at,
                                ]);
                            } else {
                                CustomerSegmentMembership::query()
                                    ->where('customer_id', $customer->id)
                                    ->where('customer_segment_id', $segment->id)
                                    ->delete();
                            }
                        }
                    }
                });
        });
    }

    public function syncManualSegments(Customer $customer, array $segmentIds): void
    {
        DB::transaction(function () use ($customer, $segmentIds) {
            $segments = CustomerSegment::query()
                ->where('type', CustomerSegment::TYPE_MANUAL)
                ->whereIn('id', $segmentIds)
                ->pluck('id')
                ->all();

            $customer->segmentMemberships()
                ->where('source', CustomerSegmentMembership::SOURCE_MANUAL)
                ->whereNotIn('customer_segment_id', $segments)
                ->delete();

            foreach ($segments as $segmentId) {
                CustomerSegmentMembership::query()->updateOrCreate(
                    [
                        'customer_id' => $customer->id,
                        'customer_segment_id' => $segmentId,
                    ],
                    [
                        'source' => CustomerSegmentMembership::SOURCE_MANUAL,
                        'matched_at' => now(),
                    ]
                );
            }
        });
    }

    public function serializeCustomerSegments(Customer $customer): array
    {
        return $customer->segments
            ->sortBy('name')
            ->values()
            ->map(fn (CustomerSegment $segment) => [
                'id' => $segment->id,
                'name' => $segment->name,
                'slug' => $segment->slug,
                'type' => $segment->type,
                'source' => $segment->pivot?->source,
                'matched_at' => optional($segment->pivot?->matched_at)->toIso8601String(),
            ])
            ->all();
    }

    public function segmentOptions(string $type = 'all'): array
    {
        return CustomerSegment::query()
            ->when($type !== 'all', fn ($query) => $query->where('type', $type))
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (CustomerSegment $segment) => [
                'value' => $segment->id,
                'label' => $segment->name,
                'type' => $segment->type,
            ])
            ->all();
    }

    public function segmentStats(CustomerSegment $segment): array
    {
        $memberships = $segment->memberships()->with('customer:id')->get();

        return [
            'total_members' => $memberships->count(),
            'manual_members' => $memberships->where('source', CustomerSegmentMembership::SOURCE_MANUAL)->count(),
            'auto_members' => $memberships->where('source', CustomerSegmentMembership::SOURCE_AUTO)->count(),
        ];
    }

    private function matchesAutoSegment(
        Customer $customer,
        CustomerSegment $segment,
        CarbonInterface $at
    ): bool {
        $config = $segment->rule_config ?? [];

        return match ($segment->slug) {
            'high_spender' => (int) $customer->loyalty_total_spent >= (int) ($config['min_total_spent'] ?? 1500000),
            'frequent_buyer' => $this->matchesFrequentBuyer($customer, $config, $at),
            'inactive_customer' => $this->matchesInactiveCustomer($customer, $config, $at),
            'credit_customer' => $this->matchesCreditCustomer($customer),
            'overdue_customer' => $this->matchesOverdueCustomer($customer),
            default => false,
        };
    }

    private function matchesFrequentBuyer(Customer $customer, array $config, CarbonInterface $at): bool
    {
        $recentDays = (int) ($config['recent_days'] ?? 45);
        $minTransactionCount = (int) ($config['min_transaction_count'] ?? 5);

        if ((int) $customer->loyalty_transaction_count < $minTransactionCount) {
            return false;
        }

        if (! $customer->last_purchase_at) {
            return false;
        }

        return $customer->last_purchase_at->gte($at->copy()->subDays($recentDays));
    }

    private function matchesInactiveCustomer(Customer $customer, array $config, CarbonInterface $at): bool
    {
        $minTransactionCount = (int) ($config['min_transaction_count'] ?? 1);
        $inactivityDays = (int) ($config['inactivity_days_min'] ?? 30);

        if ((int) $customer->loyalty_transaction_count < $minTransactionCount) {
            return false;
        }

        if (! $customer->last_purchase_at) {
            return true;
        }

        return $customer->last_purchase_at->lt($at->copy()->subDays($inactivityDays));
    }

    private function matchesCreditCustomer(Customer $customer): bool
    {
        return $customer->receivables
            ->filter(fn ($receivable) => $receivable->status !== 'paid' && $receivable->remaining > 0)
            ->isNotEmpty();
    }

    private function matchesOverdueCustomer(Customer $customer): bool
    {
        return $customer->receivables
            ->filter(fn ($receivable) => $receivable->status !== 'paid' && $receivable->due_date && now()->gt($receivable->due_date))
            ->isNotEmpty();
    }
}
