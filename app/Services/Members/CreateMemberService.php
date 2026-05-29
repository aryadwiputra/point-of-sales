<?php

declare(strict_types=1);

namespace App\Services\Members;

use App\Models\Customer;
use App\Services\Customers\CustomerRegionPayloadService;

class CreateMemberService
{
    public function __construct(
        private readonly CustomerRegionPayloadService $regionPayloadService,
        private readonly MemberLoyaltyPayloadService $loyaltyPayloadService
    ) {}

    public function execute(array $data): Customer
    {
        return Customer::query()->create([
            ...$this->loyaltyPayloadService->resolve($data),
            ...$this->regionPayloadService->resolvePayload($data),
            'name' => $data['name'],
            'no_telp' => $data['no_telp'],
            'address' => $data['address'],
        ]);
    }
}
