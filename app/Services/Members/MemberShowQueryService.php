<?php

declare(strict_types=1);

namespace App\Services\Members;

use App\Models\Customer;
use App\Services\Customers\CustomerHistoryQueryService;
use App\Services\CustomerSegmentationService;

class MemberShowQueryService
{
    public function __construct(
        private readonly CustomerHistoryQueryService $historyQueryService,
        private readonly CustomerSegmentationService $segmentationService
    ) {}

    public function execute(Customer $member): array
    {
        $member->load('segments');

        return [
            'member' => $member,
            'segments' => $this->segmentationService->serializeCustomerSegments($member),
            ...$this->historyQueryService->showActivityPayload($member),
        ];
    }
}
