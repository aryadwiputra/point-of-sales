<?php

declare(strict_types=1);

namespace App\Services\Customers;

use App\Models\Customer;

class UpdateCustomerService
{
    public function __construct(
        private readonly CustomerRegionPayloadService $regionPayloadService,
        private readonly CustomerLoyaltyPayloadService $loyaltyPayloadService
    ) {}

    public function execute(Customer $customer, array $data): Customer
    {
        $customer->update([
            ...$this->loyaltyPayloadService->resolve($data, $customer),
            ...$this->regionPayloadService->resolvePayload($data),
            'name' => $data['name'],
            'no_telp' => $data['no_telp'],
            'address' => $data['address'],
        ]);

        return $customer;
    }
}
