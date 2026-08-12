<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerSegment;
use App\Models\CustomerVoucher;
use App\Models\Transaction;
use App\Services\CustomerSegmentationService;
use App\Services\LoyaltyService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Laravolt\Indonesia\Models\City;
use Laravolt\Indonesia\Models\District;
use Laravolt\Indonesia\Models\Province;
use Laravolt\Indonesia\Models\Village;

class CustomerController extends Controller
{
    public function __construct(
        private readonly LoyaltyService $loyaltyService,
        private readonly CustomerSegmentationService $segmentationService
    ) {}

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        // get customers
        $customers = Customer::when(request()->search, function ($customers) {
            $search = request()->search;
            $customers = $customers->where(function ($query) use ($search) {
                $query
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('member_code', 'like', '%'.$search.'%');
            });
        })->latest()->paginate($this->perPage())->withQueryString();

        // return inertia
        return Inertia::render('Dashboard/Customers/Index', [
            'customers' => $customers,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        $provinces = Province::select('code', 'name')->orderBy('name')->get();

        return Inertia::render('Dashboard/Customers/Create', [
            'provinces' => $provinces,
            'tierOptions' => $this->loyaltyService->tierOptions(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store(Request $request)
    {
        /**
         * validate
         */
        $request->validate([
            'name' => 'required',
            'no_telp' => 'required|unique:customers',
            'address' => 'required',
            'is_loyalty_member' => 'nullable|boolean',
            'loyalty_tier' => ['nullable', 'string', Rule::in(array_keys($this->loyaltyService->tiers()))],
            'province_id' => 'required|string',
            'regency_id' => 'required|string',
            'district_id' => 'required|string',
            'village_id' => 'required|string',
        ]);

        $province = Province::where('code', $request->province_id)->first();
        $regency = City::where('code', $request->regency_id)->first();
        $district = District::where('code', $request->district_id)->first();
        $village = Village::where('code', $request->village_id)->first();

        // create customer
        Customer::create([
            ...$this->resolveLoyaltyPayload($request),
            'name' => $request->name,
            'no_telp' => $request->no_telp,
            'address' => $request->address,
            'province_id' => $request->province_id,
            'province_name' => $province?->name,
            'regency_id' => $request->regency_id,
            'regency_name' => $regency?->name,
            'district_id' => $request->district_id,
            'district_name' => $district?->name,
            'village_id' => $request->village_id,
            'village_name' => $village?->name,
        ]);

        // redirect
        return to_route('customers.index');
    }

    /**
     * Store a newly created customer via AJAX (returns JSON, no redirect)
     *
     * @return JsonResponse
     */
    public function storeAjax(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'no_telp' => 'required|string|unique:customers,no_telp',
            'address' => 'required|string',
            'is_loyalty_member' => 'nullable|boolean',
            'loyalty_tier' => ['nullable', 'string', Rule::in(array_keys($this->loyaltyService->tiers()))],
            'province_id' => 'nullable|string',
            'regency_id' => 'nullable|string',
            'district_id' => 'nullable|string',
            'village_id' => 'nullable|string',
        ]);

        try {
            $provinceCode = $validated['province_id'] ?? null;
            $regencyCode = $validated['regency_id'] ?? null;
            $districtCode = $validated['district_id'] ?? null;
            $villageCode = $validated['village_id'] ?? null;

            $province = $provinceCode ? Province::where('code', $provinceCode)->first() : null;
            $regency = $regencyCode ? City::where('code', $regencyCode)->first() : null;
            $district = $districtCode ? District::where('code', $districtCode)->first() : null;
            $village = $villageCode ? Village::where('code', $villageCode)->first() : null;

            $customer = Customer::create([
                ...$this->resolveLoyaltyPayload($request),
                'name' => $validated['name'],
                'no_telp' => $validated['no_telp'],
                'address' => $validated['address'],
                'province_id' => $provinceCode,
                'province_name' => $province?->name,
                'regency_id' => $regencyCode,
                'regency_name' => $regency?->name,
                'district_id' => $districtCode,
                'district_name' => $district?->name,
                'village_id' => $villageCode,
                'village_name' => $village?->name,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Pelanggan berhasil ditambahkan',
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'no_telp' => $customer->no_telp,
                    'address' => $customer->address,
                    'is_loyalty_member' => (bool) $customer->is_loyalty_member,
                    'member_code' => $customer->member_code,
                    'loyalty_tier' => $customer->loyalty_tier,
                    'loyalty_points' => (int) $customer->loyalty_points,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan pelanggan',
                'errors' => [],
            ], 500);
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit(Customer $customer)
    {
        $provinces = Province::select('code', 'name')->orderBy('name')->get();
        $regencies = $customer->province_id
            ? City::where('province_code', $customer->province_id)->select('code', 'name')->orderBy('name')->get()
            : [];
        $districts = $customer->regency_id
            ? District::where('city_code', $customer->regency_id)->select('code', 'name')->orderBy('name')->get()
            : [];
        $villages = $customer->district_id
            ? Village::where('district_code', $customer->district_id)->select('code', 'name')->orderBy('name')->get()
            : [];

        return Inertia::render('Dashboard/Customers/Edit', [
            'customer' => $customer,
            'tierOptions' => $this->loyaltyService->tierOptions(),
            'provinces' => $provinces,
            'regencies' => $regencies,
            'districts' => $districts,
            'villages' => $villages,
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request, Customer $customer)
    {
        /**
         * validate
         */
        $request->validate([
            'name' => 'required',
            'no_telp' => 'required|unique:customers,no_telp,'.$customer->id,
            'address' => 'required',
            'is_loyalty_member' => 'nullable|boolean',
            'loyalty_tier' => ['nullable', 'string', Rule::in(array_keys($this->loyaltyService->tiers()))],
            'province_id' => 'required|string',
            'regency_id' => 'required|string',
            'district_id' => 'required|string',
            'village_id' => 'required|string',
        ]);

        $province = Province::where('code', $request->province_id)->first();
        $regency = City::where('code', $request->regency_id)->first();
        $district = District::where('code', $request->district_id)->first();
        $village = Village::where('code', $request->village_id)->first();

        // update customer
        $customer->update([
            ...$this->resolveLoyaltyPayload($request, $customer),
            'name' => $request->name,
            'no_telp' => $request->no_telp,
            'address' => $request->address,
            'province_id' => $request->province_id,
            'province_name' => $province?->name,
            'regency_id' => $request->regency_id,
            'regency_name' => $regency?->name,
            'district_id' => $request->district_id,
            'district_name' => $district?->name,
            'village_id' => $request->village_id,
            'village_name' => $village?->name,
        ]);

        // redirect
        return to_route('customers.index');
    }

    public function show(Customer $customer)
    {
        $customer->load('segments');
        $stats = $this->buildStats($customer);
        $recentTransactions = $this->recentTransactions($customer);
        $frequentProducts = $this->frequentProducts($customer);
        $rewardHistory = $customer->loyaltyPointHistories()
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
        $vouchers = $customer->vouchers()
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (CustomerVoucher $voucher) => $this->loyaltyService->serializeVoucher($voucher) + [
                'is_active' => (bool) $voucher->is_active,
                'is_used' => (bool) $voucher->is_used,
            ]);

        return Inertia::render('Dashboard/Customers/Show', [
            'customer' => $customer,
            'segments' => $this->segmentationService->serializeCustomerSegments($customer),
            'manualSegmentIds' => $customer->segmentMemberships()
                ->where('source', 'manual')
                ->pluck('customer_segment_id')
                ->values()
                ->all(),
            'manualSegmentOptions' => $this->segmentationService->segmentOptions(CustomerSegment::TYPE_MANUAL),
            'stats' => $stats,
            'recentTransactions' => $recentTransactions,
            'frequentProducts' => $frequentProducts,
            'rewardHistory' => $rewardHistory,
            'vouchers' => $vouchers,
        ]);
    }

    public function syncSegments(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'segment_ids' => ['nullable', 'array'],
            'segment_ids.*' => ['integer', 'exists:customer_segments,id'],
        ]);

        $this->segmentationService->syncManualSegments($customer, $validated['segment_ids'] ?? []);

        return back()->with('success', 'Segment manual customer berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy($id)
    {
        // find customer by ID
        $customer = Customer::findOrFail($id);

        // delete customer
        $customer->delete();

        // redirect
        return back();
    }

    /**
     * Get customer purchase history
     *
     * @return JsonResponse
     */
    public function getHistory(Customer $customer)
    {
        // Get transaction statistics
        $stats = $this->buildStats($customer);
        $recentTransactions = $this->recentTransactions($customer);
        $frequentProducts = $this->frequentProducts($customer);
        $loyaltyHistory = $customer->loyaltyPointHistories()
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn ($history) => [
                'id' => $history->id,
                'type' => $history->type,
                'points_delta' => (int) $history->points_delta,
                'amount_delta' => (int) $history->amount_delta,
                'reference' => $history->reference,
                'created_at' => optional($history->created_at)?->format('d M Y H:i'),
                'notes' => $history->notes,
            ]);
        $eligibleVouchers = $customer->vouchers()
            ->where('is_active', true)
            ->where('is_used', false)
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (CustomerVoucher $voucher) => $this->loyaltyService->serializeVoucher($voucher));

        return response()->json([
            'success' => true,
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->no_telp,
            ],
            'stats' => [
                'total_transactions' => (int) ($stats->total_transactions ?? 0),
                'total_spent' => (int) ($stats->total_spent ?? 0),
                'last_visit' => $stats->last_visit ? Carbon::parse($stats->last_visit)->format('d M Y') : null,
            ],
            'loyalty' => [
                'is_member' => (bool) $customer->is_loyalty_member,
                'member_code' => $customer->member_code,
                'tier' => $customer->loyalty_tier,
                'points' => (int) $customer->loyalty_points,
                'member_since' => optional($customer->loyalty_member_since)?->format('d M Y'),
            ],
            'recent_transactions' => $recentTransactions,
            'frequent_products' => $frequentProducts,
            'loyalty_history' => $loyaltyHistory,
            'eligible_vouchers' => $eligibleVouchers,
        ]);
    }

    public function upgradeToMember(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'loyalty_tier' => ['nullable', 'string', Rule::in(array_keys($this->loyaltyService->tiers()))],
        ]);

        $customer->update([
            'is_loyalty_member' => true,
            'member_code' => $customer->member_code ?? $this->loyaltyService->issueMemberCode(),
            'loyalty_tier' => $validated['loyalty_tier'] ?? $customer->loyalty_tier ?? LoyaltyService::TIER_REGULAR,
            'loyalty_member_since' => $customer->loyalty_member_since ?? now(),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Pelanggan berhasil di-upgrade menjadi member.',
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'no_telp' => $customer->no_telp,
                    'address' => $customer->address,
                    'is_loyalty_member' => (bool) $customer->is_loyalty_member,
                    'member_code' => $customer->member_code,
                    'loyalty_tier' => $customer->loyalty_tier,
                    'loyalty_points' => (int) $customer->loyalty_points,
                ],
            ]);
        }

        return back()->with('success', 'Pelanggan berhasil di-upgrade menjadi member.');
    }

    private function resolveLoyaltyPayload(Request $request, ?Customer $customer = null): array
    {
        $isMember = $request->boolean('is_loyalty_member');
        $existingTier = $customer?->loyalty_tier ?? LoyaltyService::TIER_REGULAR;
        $requestedTier = $request->input('loyalty_tier', $existingTier);

        return [
            'is_loyalty_member' => $isMember,
            'member_code' => $isMember
                ? ($customer?->member_code ?? $this->loyaltyService->issueMemberCode())
                : $customer?->member_code,
            'loyalty_tier' => $isMember
                ? $requestedTier
                : ($customer?->loyalty_tier ?? LoyaltyService::TIER_REGULAR),
            'loyalty_member_since' => $isMember
                ? ($customer?->loyalty_member_since ?? now())
                : $customer?->loyalty_member_since,
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
            ->map(fn ($t) => [
                'id' => $t->id,
                'invoice' => $t->invoice,
                'total' => $t->grand_total,
                'payment_method' => $t->payment_method,
                'date' => Carbon::parse($t->created_at)->format('d M Y H:i'),
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
