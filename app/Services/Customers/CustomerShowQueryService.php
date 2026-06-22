<?php

declare(strict_types=1);

namespace App\Services\Customers;

use App\Models\Customer;
use App\Models\CustomerSegment;
use App\Services\CustomerSegmentationService;

class CustomerShowQueryService
{
    public function __construct(
        private readonly CustomerHistoryQueryService $historyQueryService,
        private readonly CustomerSegmentationService $segmentationService
    ) {}

    public function execute(Customer $customer): array
    {
        $customer->load('segments');

        return [
            'customer' => $customer,
            'segments' => $this->segmentationService->serializeCustomerSegments($customer),
            'manualSegmentIds' => $customer->segmentMemberships()
                ->where('source', 'manual')
                ->pluck('customer_segment_id')
                ->values()
                ->all(),
            'manualSegmentOptions' => $this->segmentationService->segmentOptions(CustomerSegment::TYPE_MANUAL),
            ...$this->historyQueryService->showActivityPayload($customer),
        ];
    }
}
