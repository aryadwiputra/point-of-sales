<?php

declare(strict_types=1);

namespace App\Services\Customers;

use App\Models\Customer;
use App\Services\LoyaltyService;

class UpgradeCustomerToMemberService
{
    public function __construct(
        private readonly LoyaltyService $loyaltyService
    ) {}

    public function execute(Customer $customer, array $data): Customer
    {
        $customer->update([
            'is_loyalty_member' => true,
            'member_code' => $customer->member_code ?? $this->loyaltyService->issueMemberCode(),
            'loyalty_tier' => $data['loyalty_tier'] ?? $customer->loyalty_tier ?? LoyaltyService::TIER_REGULAR,
            'loyalty_member_since' => $customer->loyalty_member_since ?? now(),
        ]);

        return $customer;
    }
}
