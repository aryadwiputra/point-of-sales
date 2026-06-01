<?php

declare(strict_types=1);

namespace App\Services\CustomerSegments;

use App\Models\Customer;
use App\Models\CustomerSegment;
use App\Services\CustomerSegmentationService;

class CustomerSegmentShowQueryService
{
    public function __construct(
        private readonly CustomerSegmentationService $segmentationService
    ) {}

    public function execute(CustomerSegment $segment): array
    {
        $segment->load([
            'memberships.customer' => fn ($query) => $query->select('id', 'name', 'no_telp', 'is_loyalty_member', 'loyalty_tier', 'last_purchase_at'),
        ]);

        return [
            'segment' => [
                ...$segment->toArray(),
                'stats' => $this->segmentationService->segmentStats($segment),
                'memberships' => $segment->memberships
                    ->sortByDesc('matched_at')
                    ->values()
                    ->map(fn ($membership) => [
                        'id' => $membership->id,
                        'source' => $membership->source,
                        'matched_at' => optional($membership->matched_at)?->toIso8601String(),
                        'customer' => $membership->customer ? [
                            'id' => $membership->customer->id,
                            'name' => $membership->customer->name,
                            'no_telp' => $membership->customer->no_telp,
                            'is_loyalty_member' => (bool) $membership->customer->is_loyalty_member,
                            'loyalty_tier' => $membership->customer->loyalty_tier,
                            'last_purchase_at' => optional($membership->customer->last_purchase_at)?->toIso8601String(),
                        ] : null,
                    ])
                    ->all(),
            ],
            'customers' => Customer::query()
                ->orderBy('name')
                ->get(['id', 'name', 'no_telp', 'is_loyalty_member', 'loyalty_tier']),
        ];
    }
}
