<?php

declare(strict_types=1);

namespace App\Services\Customers;

use App\Models\Customer;
use App\Services\CustomerSegmentationService;

class SyncCustomerSegmentsService
{
    public function __construct(
        private readonly CustomerSegmentationService $segmentationService
    ) {}

    public function execute(Customer $customer, array $segmentIds): void
    {
        $this->segmentationService->syncManualSegments($customer, $segmentIds);
    }
}
