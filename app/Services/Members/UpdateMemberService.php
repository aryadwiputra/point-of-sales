<?php

declare(strict_types=1);

namespace App\Services\Members;

use App\Models\Customer;
use App\Services\Customers\CustomerRegionPayloadService;

class UpdateMemberService
{
    public function __construct(
        private readonly CustomerRegionPayloadService $regionPayloadService,
        private readonly MemberLoyaltyPayloadService $loyaltyPayloadService
    ) {}

    public function execute(Customer $member, array $data): Customer
    {
        $member->update([
            ...$this->loyaltyPayloadService->resolve($data, $member),
            ...$this->regionPayloadService->resolvePayload($data),
            'name' => $data['name'],
            'no_telp' => $data['no_telp'],
            'address' => $data['address'],
        ]);

        return $member;
    }
}
