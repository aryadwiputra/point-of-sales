<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerSegment;
use App\Services\CustomerSegmentationService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CustomerSegmentController extends Controller
{
    public function __construct(
        private readonly CustomerSegmentationService $segmentationService
    ) {}

    public function index(Request $request)
    {
        $this->segmentationService->ensureDefaultAutoSegments();

        $filters = [
            'search' => $request->input('search'),
            'type' => $request->input('type'),
        ];

        $segments = CustomerSegment::query()
            ->withCount('memberships')
            ->when($filters['search'], fn ($query, $search) => $query->where('name', 'like', '%'.$search.'%'))
            ->when($filters['type'], fn ($query, $type) => $query->where('type', $type))
            ->orderBy('type')
            ->orderBy('name')
            ->paginate(10)->withQueryString()
            ->withQueryString();

        return Inertia::render('Dashboard/CustomerSegments/Index', [
            'segments' => $segments,
            'filters' => $filters,
        ]);
    }

    public function create()
    {
        return Inertia::render('Dashboard/CustomerSegments/Create', [
            'segment' => null,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateSegment($request);
        $segment = CustomerSegment::query()->create($validated);

        if ($segment->type === CustomerSegment::TYPE_AUTO) {
            $this->segmentationService->syncAutoSegments();
        }

        return redirect()
            ->route('customer-segments.index')
            ->with('success', 'Segment customer berhasil dibuat.');
    }

    public function show(CustomerSegment $customerSegment)
    {
        $customerSegment->load([
            'memberships.customer' => fn ($query) => $query->select('id', 'name', 'no_telp', 'is_loyalty_member', 'loyalty_tier', 'last_purchase_at'),
        ]);

        return Inertia::render('Dashboard/CustomerSegments/Show', [
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

    public function edit(CustomerSegment $customerSegment)
    {
        return Inertia::render('Dashboard/CustomerSegments/Edit', [
            'segment' => $customerSegment,
        ]);
    }

    public function update(Request $request, CustomerSegment $customerSegment)
    {
        $validated = $this->validateSegment($request, $customerSegment);
        $customerSegment->update($validated);

        if ($customerSegment->type === CustomerSegment::TYPE_AUTO) {
            $this->segmentationService->syncAutoSegments();
        }

        return redirect()
            ->route('customer-segments.show', $customerSegment)
            ->with('success', 'Segment customer berhasil diperbarui.');
    }

    public function destroy(CustomerSegment $customerSegment)
    {
        $customerSegment->delete();

        return redirect()
            ->route('customer-segments.index')
            ->with('success', 'Segment customer berhasil dihapus.');
    }

    public function storeMember(Request $request, CustomerSegment $customerSegment)
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

        return back()->with('success', 'Customer ditambahkan ke segment manual.');
    }

    public function destroyMember(CustomerSegment $customerSegment, Customer $customer)
    {
        abort_if($customerSegment->type !== CustomerSegment::TYPE_MANUAL, 422, 'Segment otomatis tidak dapat diubah manual.');

        $manualIds = $customer->segmentMemberships()
            ->where('source', 'manual')
            ->where('customer_segment_id', '!=', $customerSegment->id)
            ->pluck('customer_segment_id')
            ->values()
            ->all();
        $this->segmentationService->syncManualSegments($customer, $manualIds);

        return back()->with('success', 'Customer dihapus dari segment manual.');
    }

    private function validateSegment(Request $request, ?CustomerSegment $segment = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in([CustomerSegment::TYPE_MANUAL, CustomerSegment::TYPE_AUTO])],
            'is_active' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
            'auto_rule_type' => ['nullable', Rule::in([
                CustomerSegment::RULE_SPENDING,
                CustomerSegment::RULE_PURCHASE_FREQUENCY,
                CustomerSegment::RULE_RECEIVABLE_BEHAVIOR,
            ])],
            'rule_config' => ['nullable', 'array'],
            'rule_config.min_total_spent' => ['nullable', 'integer', 'min:0'],
            'rule_config.min_transaction_count' => ['nullable', 'integer', 'min:0'],
            'rule_config.recent_days' => ['nullable', 'integer', 'min:1'],
            'rule_config.inactivity_days_min' => ['nullable', 'integer', 'min:1'],
            'rule_config.require_outstanding_receivable' => ['nullable', 'boolean'],
            'rule_config.overdue_only' => ['nullable', 'boolean'],
        ]);

        $validated['slug'] = $segment?->slug ?? Str::slug($validated['name']);
        if (! $segment && CustomerSegment::query()->where('slug', $validated['slug'])->exists()) {
            $validated['slug'] .= '-'.Str::lower(Str::random(4));
        }

        if ($validated['type'] === CustomerSegment::TYPE_MANUAL) {
            $validated['auto_rule_type'] = null;
            $validated['rule_config'] = null;
        } else {
            $validated['rule_config'] = $validated['rule_config'] ?? [];
        }

        $validated['is_active'] = (bool) ($validated['is_active'] ?? false);

        return $validated;
    }
}
