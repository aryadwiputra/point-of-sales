<?php

declare(strict_types=1);

namespace App\Services\Customers;

use App\Models\Customer;

class CreateCustomerService
{
    public function __construct(
        private readonly CustomerRegionPayloadService $regionPayloadService,
        private readonly CustomerLoyaltyPayloadService $loyaltyPayloadService
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
