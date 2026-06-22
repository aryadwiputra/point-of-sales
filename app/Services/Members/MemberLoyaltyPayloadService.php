<?php

declare(strict_types=1);

namespace App\Services\Members;

use App\Models\Customer;
use App\Services\LoyaltyService;

class MemberLoyaltyPayloadService
{
    public function __construct(
        private readonly LoyaltyService $loyaltyService
    ) {}

    public function resolve(array $data, ?Customer $member = null): array
    {
        $isMember = filter_var($data['is_loyalty_member'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $existingTier = $member?->loyalty_tier ?? LoyaltyService::TIER_REGULAR;
        $requestedTier = $data['loyalty_tier'] ?? $existingTier;

        if ($isMember) {
            return [
                'is_loyalty_member' => true,
                'member_code' => $member?->member_code ?? $this->loyaltyService->issueMemberCode(),
                'loyalty_tier' => $requestedTier,
                'loyalty_member_since' => $member?->loyalty_member_since ?? now(),
            ];
        }

        return [
            'is_loyalty_member' => false,
            'member_code' => $member?->member_code,
            'loyalty_tier' => $member?->loyalty_tier ?? $requestedTier,
            'loyalty_member_since' => $member?->loyalty_member_since,
        ];
    }
}
