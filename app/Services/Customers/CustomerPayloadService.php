<?php

declare(strict_types=1);

namespace App\Services\Customers;

use App\Models\Customer;
use App\Services\LoyaltyService;

class CustomerPayloadService
{
    public function __construct(
        private readonly CustomerRegionPayloadService $regionPayloadService,
        private readonly LoyaltyService $loyaltyService
    ) {}

    public function createPayload(): array
    {
        return [
            'provinces' => $this->regionPayloadService->provinces(),
            'tierOptions' => $this->loyaltyService->tierOptions(),
        ];
    }

    public function editPayload(Customer $customer): array
    {
        return [
            'customer' => $customer,
            'tierOptions' => $this->loyaltyService->tierOptions(),
            'provinces' => $this->regionPayloadService->provinces(),
            ...$this->regionPayloadService->selectedOptions($customer),
        ];
    }

    public function jsonCustomer(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'no_telp' => $customer->no_telp,
            'address' => $customer->address,
            'is_loyalty_member' => (bool) $customer->is_loyalty_member,
            'member_code' => $customer->member_code,
            'loyalty_tier' => $customer->loyalty_tier,
            'loyalty_points' => (int) $customer->loyalty_points,
        ];
    }
}
