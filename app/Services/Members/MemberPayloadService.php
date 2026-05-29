<?php

declare(strict_types=1);

namespace App\Services\Members;

use App\Models\Customer;
use App\Services\Customers\CustomerRegionPayloadService;
use App\Services\LoyaltyService;

class MemberPayloadService
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

    public function editPayload(Customer $member): array
    {
        return [
            'member' => $member,
            'tierOptions' => $this->loyaltyService->tierOptions(),
            'provinces' => $this->regionPayloadService->provinces(),
            ...$this->regionPayloadService->selectedOptions($member),
        ];
    }
}
