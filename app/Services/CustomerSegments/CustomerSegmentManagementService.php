<?php

declare(strict_types=1);

namespace App\Services\CustomerSegments;

use App\Models\Customer;
use App\Models\CustomerSegment;
use App\Models\CustomerSegmentMembership;
use App\Services\CustomerSegmentationService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerSegmentManagementService
{
    public function __construct(
        private readonly CustomerSegmentationService $segmentationService
    ) {}

    public function create(array $data): CustomerSegment
    {
        $segment = CustomerSegment::query()->create($this->normalize($data));
        $this->syncAutoSegmentsWhenNeeded($segment);

        return $segment;
    }

    public function update(CustomerSegment $segment, array $data): CustomerSegment
    {
        $segment->update($this->normalize($data, $segment));
        $this->syncAutoSegmentsWhenNeeded($segment);

        return $segment->fresh();
    }

    public function delete(CustomerSegment $segment): void
    {
        $segment->delete();
    }

    public function addMember(CustomerSegment $segment, int $customerId): void
    {
        $this->ensureManual($segment);
        $customer = Customer::query()->findOrFail($customerId);
        $manualIds = $customer->segmentMemberships()
            ->where('source', CustomerSegmentMembership::SOURCE_MANUAL)
            ->pluck('customer_segment_id')
            ->push($segment->id)
            ->unique()
            ->values()
            ->all();

        $this->segmentationService->syncManualSegments($customer, $manualIds);
    }

    public function removeMember(CustomerSegment $segment, Customer $customer): void
    {
        $this->ensureManual($segment);
        $manualIds = $customer->segmentMemberships()
            ->where('source', CustomerSegmentMembership::SOURCE_MANUAL)
            ->where('customer_segment_id', '!=', $segment->id)
            ->pluck('customer_segment_id')
            ->values()
            ->all();

        $this->segmentationService->syncManualSegments($customer, $manualIds);
    }

    private function normalize(array $data, ?CustomerSegment $segment = null): array
    {
        $data['slug'] = $segment?->slug ?? Str::slug($data['name']);

        if (! $segment && CustomerSegment::query()->where('slug', $data['slug'])->exists()) {
            $data['slug'] .= '-'.Str::lower(Str::random(4));
        }

        if ($data['type'] === CustomerSegment::TYPE_MANUAL) {
            $data['auto_rule_type'] = null;
            $data['rule_config'] = null;
        } else {
            $data['rule_config'] = $data['rule_config'] ?? [];
        }

        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        return $data;
    }

    private function syncAutoSegmentsWhenNeeded(CustomerSegment $segment): void
    {
        if ($segment->type === CustomerSegment::TYPE_AUTO) {
            $this->segmentationService->syncAutoSegments();
        }
    }

    private function ensureManual(CustomerSegment $segment): void
    {
        if ($segment->type !== CustomerSegment::TYPE_MANUAL) {
            throw ValidationException::withMessages([
                'segment' => 'Segment otomatis tidak dapat diubah manual.',
            ]);
        }
    }
}
