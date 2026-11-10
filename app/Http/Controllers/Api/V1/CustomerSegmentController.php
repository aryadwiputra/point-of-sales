<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Apps\CustomerSegmentController as AppsCustomerSegmentController;
use App\Http\Traits\ApiResponder;
use App\Models\Customer;
use App\Models\CustomerSegment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerSegmentController extends AppsCustomerSegmentController
{
    use ApiResponder;

    /**
     * GET /api/v1/customer-segments
     */
    public function listSegments(Request $request): JsonResponse
    {
        $this->segmentationService->ensureDefaultAutoSegments();

        $segments = CustomerSegment::query()
            ->withCount('memberships')
            ->when($request->input('search'), fn ($query, $search) => $query->where('name', 'like', '%'.$search.'%'))
            ->when($request->input('type'), fn ($query, $type) => $query->where('type', $type))
            ->orderBy('type')
            ->orderBy('name')
            ->paginate($this->perPage())
            ->withQueryString();

        return $this->paginated($segments, [
            'rule_types' => [
                ['value' => CustomerSegment::RULE_SPENDING, 'label' => 'Total belanja'],
                ['value' => CustomerSegment::RULE_PURCHASE_FREQUENCY, 'label' => 'Frekuensi pembelian'],
                ['value' => CustomerSegment::RULE_RECEIVABLE_BEHAVIOR, 'label' => 'Perilaku piutang'],
            ],
        ]);
    }

    /**
     * POST /api/v1/customer-segments
     */
    public function storeSegment(Request $request): JsonResponse
    {
        $validated = $this->validateSegment($request);
        $segment = CustomerSegment::query()->create($validated);

        if ($segment->type === CustomerSegment::TYPE_AUTO) {
            $this->segmentationService->syncAutoSegments();
        }

        return $this->created($segment);
    }

    /**
     * GET /api/v1/customer-segments/{customerSegment}
     */
    public function showSegment(CustomerSegment $customerSegment): JsonResponse
    {
        $customerSegment->load([
            'memberships.customer' => fn ($query) => $query->select('id', 'name', 'no_telp', 'is_loyalty_member', 'loyalty_tier', 'last_purchase_at'),
        ]);

        return $this->ok([
            'segment' => [
                ...$customerSegment->toArray(),
                'stats' => $this->segmentationService->segmentStats($customerSegment),
                'memberships' => $customerSegment->memberships
                    ->sortByDesc('matched_at')
                    ->values()
                    ->map(fn ($membership) => [
                        'id' => $membership->id,
                        'source' => $membership->source,
                        'matched_at' => optional($membership->matched_at)?->toIso8601String(),
                        'customer' => $membership->customer ? [
                            'id' => $membership->customer->id,
                            'name' => $membership->customer->name,
                            'no_telp' => $membership->customer->no_telp,
                            'is_loyalty_member' => (bool) $membership->customer->is_loyalty_member,
                            'loyalty_tier' => $membership->customer->loyalty_tier,
                            'last_purchase_at' => optional($membership->customer->last_purchase_at)?->toIso8601String(),
                        ] : null,
                    ])
                    ->all(),
            ],
            'customers' => Customer::query()
                ->orderBy('name')
                ->get(['id', 'name', 'no_telp', 'is_loyalty_member', 'loyalty_tier']),
        ]);
    }

    /**
     * PUT /api/v1/customer-segments/{customerSegment}
     */
    public function updateSegment(Request $request, CustomerSegment $customerSegment): JsonResponse
    {
        $validated = $this->validateSegment($request, $customerSegment);
        $customerSegment->update($validated);

        if ($customerSegment->type === CustomerSegment::TYPE_AUTO) {
            $this->segmentationService->syncAutoSegments();
        }

        return $this->ok($customerSegment);
    }

    /**
     * DELETE /api/v1/customer-segments/{customerSegment}
     */
    public function destroySegment(CustomerSegment $customerSegment): JsonResponse
    {
        $customerSegment->delete();

        return $this->noContent();
    }

    /**
     * POST /api/v1/customer-segments/{customerSegment}/members
     */
    public function addMember(Request $request, CustomerSegment $customerSegment): JsonResponse
    {
        abort_if($customerSegment->type !== CustomerSegment::TYPE_MANUAL, 422, 'Segment otomatis tidak dapat diubah manual.');

        $validated = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
        ]);

        $customer = Customer::findOrFail($validated['customer_id']);
        $manualIds = $customer->segmentMemberships()
            ->where('source', 'manual')
            ->pluck('customer_segment_id')
            ->push($customerSegment->id)
            ->unique()
            ->values()
            ->all();
        $this->segmentationService->syncManualSegments($customer, $manualIds);

        return $this->ok(['added' => true]);
    }

    /**
     * DELETE /api/v1/customer-segments/{customerSegment}/members/{customer}
     */
    public function removeMember(CustomerSegment $customerSegment, Customer $customer): JsonResponse
    {
        abort_if($customerSegment->type !== CustomerSegment::TYPE_MANUAL, 422, 'Segment otomatis tidak dapat diubah manual.');

        $manualIds = $customer->segmentMemberships()
            ->where('source', 'manual')
            ->where('customer_segment_id', '!=', $customerSegment->id)
            ->pluck('customer_segment_id')
            ->values()
            ->all();
        $this->segmentationService->syncManualSegments($customer, $manualIds);

        return $this->ok(['removed' => true]);
    }
}
