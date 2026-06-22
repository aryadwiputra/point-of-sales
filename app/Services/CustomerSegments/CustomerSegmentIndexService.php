<?php

declare(strict_types=1);

namespace App\Services\CustomerSegments;

use App\Models\CustomerSegment;
use App\Services\CustomerSegmentationService;

class CustomerSegmentIndexService
{
    public function __construct(
        private readonly CustomerSegmentationService $segmentationService
    ) {}

    public function execute(array $filters): array
    {
        $this->segmentationService->ensureDefaultAutoSegments();

        return [
            'segments' => CustomerSegment::query()
                ->withCount('memberships')
                ->when($filters['search'], fn ($query, $search) => $query->where('name', 'like', '%'.$search.'%'))
                ->when($filters['type'], fn ($query, $type) => $query->where('type', $type))
                ->orderBy('type')
                ->orderBy('name')
                ->paginate(10)
                ->withQueryString(),
            'filters' => $filters,
        ];
    }
}
