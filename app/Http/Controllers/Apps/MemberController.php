<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerVoucher;
use App\Models\Transaction;
use App\Services\CustomerSegmentationService;
use App\Services\LoyaltyService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Laravolt\Indonesia\Models\City;
use Laravolt\Indonesia\Models\District;
use Laravolt\Indonesia\Models\Province;
use Laravolt\Indonesia\Models\Village;

class MemberController extends Controller
{
    public function __construct(
        private readonly LoyaltyService $loyaltyService,
        private readonly CustomerSegmentationService $segmentationService
    ) {}

    public function index(Request $request)
    {
        $status = $request->string('status')->value() ?: 'active';
        $search = trim((string) $request->string('search')->value());
        $tier = trim((string) $request->string('tier')->value());

        $baseQuery = Customer::query()
            ->where(function ($query) {
                $query
                    ->where('is_loyalty_member', true)
                    ->orWhereNotNull('member_code');
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($customerQuery) use ($search) {
                    $customerQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('member_code', 'like', "%{$search}%");
                });
            })
            ->when($tier !== '', function ($query) use ($tier) {
                $query->where('loyalty_tier', $tier);
            })
            ->when($status === 'active', function ($query) {
                $query->where('is_loyalty_member', true);
            })
            ->when($status === 'inactive', function ($query) {
                $query
                    ->where('is_loyalty_member', false)
                    ->whereNotNull('member_code');
            });

        $members = (clone $baseQuery)
            ->latest()
            ->paginate(10)->withQueryString()
            ->withQueryString();

        $summaryQuery = Customer::query()
            ->where(function ($query) {
                $query
                    ->where('is_loyalty_member', true)
                    ->orWhereNotNull('member_code');
            });

        $memberRevenue = (int) Transaction::query()
            ->whereIn('customer_id', (clone $summaryQuery)->pluck('id'))
            ->sum('grand_total');

        $repeatMembers = (clone $summaryQuery)
            ->where('loyalty_transaction_count', '>', 1)
            ->count();

        $totalMembers = (clone $summaryQuery)->count();
        $activeMembers = (clone $summaryQuery)->where('is_loyalty_member', true)->count();
        $topMember = (clone $summaryQuery)
            ->orderByDesc('loyalty_total_spent')
            ->first(['id', 'name', 'loyalty_total_spent']);

        return Inertia::render('Dashboard/Members/Index', [
            'members' => $members,
            'filters' => [
                'search' => $search,
                'tier' => $tier,
                'status' => $status,
            ],
            'tierOptions' => $this->loyaltyService->tierOptions(),
            'summary' => [
                'total_members' => $totalMembers,
                'active_members' => $activeMembers,
                'member_revenue' => $memberRevenue,
                'repeat_members' => $repeatMembers,
                'repeat_rate' => $totalMembers > 0 ? round(($repeatMembers / $totalMembers) * 100, 1) : 0,
                'top_member' => $topMember ? [
                    'id' => $topMember->id,
                    'name' => $topMember->name,
                    'total_spent' => (int) $topMember->loyalty_total_spent,
                ] : null,
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('Dashboard/Members/Create', [
            'provinces' => Province::select('code', 'name')->orderBy('name')->get(),
            'tierOptions' => $this->loyaltyService->tierOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateMemberRequest($request);
        $regionPayload = $this->resolveRegionPayload($validated);

        Customer::create([
            ...$this->memberPayloadFromRequest($request),
            ...$regionPayload,
            'name' => $validated['name'],
            'no_telp' => $validated['no_telp'],
            'address' => $validated['address'],
        ]);

        return to_route('members.index');
    }

    public function show(Customer $member)
    {
        $member->load('segments');
        $stats = $this->buildStats($member);
        $recentTransactions = $this->recentTransactions($member);
        $frequentProducts = $this->frequentProducts($member);
        $rewardHistory = $member->loyaltyPointHistories()
            ->latest()
            ->limit(15)
            ->get()
            ->map(fn ($history) => [
                'id' => $history->id,
                'type' => $history->type,
                'points_delta' => (int) $history->points_delta,
                'amount_delta' => (int) $history->amount_delta,
                'balance_after' => (int) $history->balance_after,
                'reference' => $history->reference,
                'notes' => $history->notes,
                'created_at' => optional($history->created_at)?->format('d M Y H:i'),
            ]);
        $vouchers = $member->vouchers()
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (CustomerVoucher $voucher) => $this->loyaltyService->serializeVoucher($voucher) + [
                'is_active' => (bool) $voucher->is_active,
                'is_used' => (bool) $voucher->is_used,
            ]);

        return Inertia::render('Dashboard/Members/Show', [
            'member' => $member,
            'segments' => $this->segmentationService->serializeCustomerSegments($member),
            'stats' => $stats,
            'recentTransactions' => $recentTransactions,
            'frequentProducts' => $frequentProducts,
            'rewardHistory' => $rewardHistory,
            'vouchers' => $vouchers,
        ]);
    }

    public function edit(Customer $member)
    {
        return Inertia::render('Dashboard/Members/Edit', [
            'member' => $member,
            'tierOptions' => $this->loyaltyService->tierOptions(),
            'provinces' => Province::select('code', 'name')->orderBy('name')->get(),
            'regencies' => $member->province_id
                ? City::where('province_code', $member->province_id)->select('code', 'name')->orderBy('name')->get()
                : [],
            'districts' => $member->regency_id
                ? District::where('city_code', $member->regency_id)->select('code', 'name')->orderBy('name')->get()
                : [],
            'villages' => $member->district_id
                ? Village::where('district_code', $member->district_id)->select('code', 'name')->orderBy('name')->get()
                : [],
        ]);
    }

    public function update(Request $request, Customer $member)
    {
        $validated = $this->validateMemberRequest($request, $member);
        $regionPayload = $this->resolveRegionPayload($validated);

        $member->update([
            ...$this->memberPayloadFromRequest($request, $member),
            ...$regionPayload,
            'name' => $validated['name'],
            'no_telp' => $validated['no_telp'],
            'address' => $validated['address'],
        ]);

        return to_route('members.show', $member);
    }

    private function validateMemberRequest(Request $request, ?Customer $customer = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'no_telp' => ['required', 'string', Rule::unique('customers', 'no_telp')->ignore($customer?->id)],
            'address' => ['required', 'string'],
            'is_loyalty_member' => ['nullable', 'boolean'],
            'loyalty_tier' => ['nullable', 'string', Rule::in(array_keys($this->loyaltyService->tiers()))],
            'province_id' => ['required', 'string'],
            'regency_id' => ['required', 'string'],
            'district_id' => ['required', 'string'],
            'village_id' => ['required', 'string'],
        ]);
    }

    private function resolveRegionPayload(array $validated): array
    {
        $province = Province::where('code', $validated['province_id'])->first();
        $regency = City::where('code', $validated['regency_id'])->first();
        $district = District::where('code', $validated['district_id'])->first();
        $village = Village::where('code', $validated['village_id'])->first();

        return [
            'province_id' => $validated['province_id'],
            'province_name' => $province?->name,
            'regency_id' => $validated['regency_id'],
            'regency_name' => $regency?->name,
            'district_id' => $validated['district_id'],
            'district_name' => $district?->name,
            'village_id' => $validated['village_id'],
            'village_name' => $village?->name,
        ];
    }

    private function memberPayloadFromRequest(Request $request, ?Customer $customer = null): array
    {
        $isMember = $request->boolean('is_loyalty_member', true);
        $existingTier = $customer?->loyalty_tier ?? LoyaltyService::TIER_REGULAR;
        $requestedTier = $request->input('loyalty_tier', $existingTier);

        if ($isMember) {
            return [
                'is_loyalty_member' => true,
                'member_code' => $customer?->member_code ?? $this->loyaltyService->issueMemberCode(),
                'loyalty_tier' => $requestedTier,
                'loyalty_member_since' => $customer?->loyalty_member_since ?? now(),
            ];
        }

        return [
            'is_loyalty_member' => false,
            'member_code' => $customer?->member_code,
            'loyalty_tier' => $customer?->loyalty_tier ?? $requestedTier,
            'loyalty_member_since' => $customer?->loyalty_member_since,
        ];
    }

    private function buildStats(Customer $customer)
    {
        return Transaction::where('customer_id', $customer->id)
            ->selectRaw('
                COUNT(*) as total_transactions,
                SUM(grand_total) as total_spent,
                MAX(created_at) as last_visit
            ')
            ->first();
    }

    private function recentTransactions(Customer $customer)
    {
        return Transaction::where('customer_id', $customer->id)
            ->select('id', 'invoice', 'grand_total', 'payment_method', 'created_at')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn ($transaction) => [
                'id' => $transaction->id,
                'invoice' => $transaction->invoice,
                'total' => $transaction->grand_total,
                'payment_method' => $transaction->payment_method,
                'date' => $transaction->created_at?->toISOString(),
            ]);
    }

    private function frequentProducts(Customer $customer)
    {
        return Transaction::where('customer_id', $customer->id)
            ->join('transaction_details', 'transactions.id', '=', 'transaction_details.transaction_id')
            ->join('products', 'transaction_details.product_id', '=', 'products.id')
            ->selectRaw('products.id, products.title, SUM(transaction_details.qty) as total_qty')
            ->groupBy('products.id', 'products.title')
            ->orderByDesc('total_qty')
            ->limit(3)
            ->get();
    }
}
