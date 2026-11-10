<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Apps\MemberController as AppsMemberController;
use App\Http\Traits\ApiResponder;
use App\Models\Customer;
use App\Models\CustomerVoucher;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberController extends AppsMemberController
{
    use ApiResponder;

    /**
     * GET /api/v1/members
     */
    public function listMembers(Request $request): JsonResponse
    {
        $status = $request->string('status')->value() ?: 'active';
        $search = trim((string) $request->string('search')->value());
        $tier = trim((string) $request->string('tier')->value());

        $baseQuery = Customer::query()
            ->where(function ($query) {
                $query->where('is_loyalty_member', true)->orWhereNotNull('member_code');
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($customerQuery) use ($search) {
                    $customerQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('member_code', 'like', "%{$search}%");
                });
            })
            ->when($tier !== '', fn ($query) => $query->where('loyalty_tier', $tier))
            ->when($status === 'active', fn ($query) => $query->where('is_loyalty_member', true))
            ->when($status === 'inactive', function ($query) {
                $query->where('is_loyalty_member', false)->whereNotNull('member_code');
            });

        $members = (clone $baseQuery)->latest()->paginate($this->perPage())->withQueryString();

        $summaryQuery = Customer::query()
            ->where(function ($query) {
                $query->where('is_loyalty_member', true)->orWhereNotNull('member_code');
            });

        $memberIds = (clone $summaryQuery)->pluck('id');
        $memberRevenue = (int) Transaction::query()->whereIn('customer_id', $memberIds)->sum('grand_total');
        $repeatMembers = (clone $summaryQuery)->where('loyalty_transaction_count', '>', 1)->count();
        $totalMembers = (clone $summaryQuery)->count();
        $activeMembers = (clone $summaryQuery)->where('is_loyalty_member', true)->count();
        $topMember = (clone $summaryQuery)->orderByDesc('loyalty_total_spent')->first(['id', 'name', 'loyalty_total_spent']);

        return $this->ok([
            'members' => $members,
            'filters' => ['search' => $search, 'tier' => $tier, 'status' => $status],
            'tier_options' => $this->loyaltyService->tierOptions(),
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

    /**
     * GET /api/v1/members/create-data
     */
    public function createData(): JsonResponse
    {
        return $this->ok([
            'tier_options' => $this->loyaltyService->tierOptions(),
        ]);
    }

    /**
     * POST /api/v1/members
     */
    public function storeMember(Request $request): JsonResponse
    {
        $validated = $this->validateMemberRequest($request);
        $regionPayload = $this->resolveRegionPayload($validated);

        $member = Customer::create([
            ...$this->memberPayloadFromRequest($request),
            ...$regionPayload,
            'name' => $validated['name'],
            'no_telp' => $validated['no_telp'],
            'address' => $validated['address'],
        ]);

        return $this->created($member);
    }

    /**
     * GET /api/v1/members/{member}
     */
    public function showMember(Customer $member): JsonResponse
    {
        $member->load('segments');
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

        return $this->ok([
            'member' => $member,
            'segments' => $this->segmentationService->serializeCustomerSegments($member),
            'stats' => $this->buildStats($member),
            'recent_transactions' => $this->recentTransactions($member),
            'frequent_products' => $this->frequentProducts($member),
            'reward_history' => $rewardHistory,
            'vouchers' => $vouchers,
        ]);
    }

    /**
     * PUT /api/v1/members/{member}
     */
    public function updateMember(Request $request, Customer $member): JsonResponse
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

        return $this->ok($member->fresh());
    }
}
