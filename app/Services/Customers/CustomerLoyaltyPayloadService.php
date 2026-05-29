<?php

declare(strict_types=1);

namespace App\Services\Customers;

use App\Models\Customer;
use App\Services\LoyaltyService;

class CustomerLoyaltyPayloadService
{
    public function __construct(
        private readonly LoyaltyService $loyaltyService
    ) {}

    public function resolve(array $data, ?Customer $customer = null): array
    {
        $isMember = filter_var($data['is_loyalty_member'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $existingTier = $customer?->loyalty_tier ?? LoyaltyService::TIER_REGULAR;
        $requestedTier = $data['loyalty_tier'] ?? $existingTier;

        return [
            'is_loyalty_member' => $isMember,
            'member_code' => $isMember
                ? ($customer?->member_code ?? $this->loyaltyService->issueMemberCode())
                : $customer?->member_code,
            'loyalty_tier' => $isMember
                ? $requestedTier
                : ($customer?->loyalty_tier ?? LoyaltyService::TIER_REGULAR),
            'loyalty_member_since' => $isMember
                ? ($customer?->loyalty_member_since ?? now())
                : $customer?->loyalty_member_since,
        ];
    }
}
