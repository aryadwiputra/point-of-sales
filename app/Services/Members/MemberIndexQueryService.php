<?php

declare(strict_types=1);

namespace App\Services\Members;

use App\Models\Customer;
use App\Models\Transaction;
use App\Services\LoyaltyService;
use Illuminate\Database\Eloquent\Builder;

class MemberIndexQueryService
{
    public function __construct(
        private readonly LoyaltyService $loyaltyService
    ) {}

    public function execute(array $filters): array
    {
        $search = $filters['search'] ?? '';
        $tier = $filters['tier'] ?? '';
        $status = $filters['status'] ?? 'active';

        $baseQuery = $this->memberQuery()
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $customerQuery) use ($search) {
                    $customerQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('member_code', 'like', "%{$search}%");
                });
            })
            ->when($tier !== '', fn (Builder $query) => $query->where('loyalty_tier', $tier))
            ->when($status === 'active', fn (Builder $query) => $query->where('is_loyalty_member', true))
            ->when($status === 'inactive', function (Builder $query) {
                $query
                    ->where('is_loyalty_member', false)
                    ->whereNotNull('member_code');
            });

        return [
            'members' => (clone $baseQuery)
                ->latest()
                ->paginate(10)
                ->withQueryString(),
            'filters' => [
                'search' => $search,
                'tier' => $tier,
                'status' => $status,
            ],
            'tierOptions' => $this->loyaltyService->tierOptions(),
            'summary' => $this->summary(),
        ];
    }

    private function summary(): array
    {
        $summaryQuery = $this->memberQuery();
        $totalMembers = (clone $summaryQuery)->count();
        $repeatMembers = (clone $summaryQuery)
            ->where('loyalty_transaction_count', '>', 1)
            ->count();
        $topMember = (clone $summaryQuery)
            ->orderByDesc('loyalty_total_spent')
            ->first(['id', 'name', 'loyalty_total_spent']);

        return [
            'total_members' => $totalMembers,
            'active_members' => (clone $summaryQuery)->where('is_loyalty_member', true)->count(),
            'member_revenue' => (int) Transaction::query()
                ->whereIn('customer_id', (clone $summaryQuery)->pluck('id'))
                ->sum('grand_total'),
            'repeat_members' => $repeatMembers,
            'repeat_rate' => $totalMembers > 0 ? round(($repeatMembers / $totalMembers) * 100, 1) : 0,
            'top_member' => $topMember ? [
                'id' => $topMember->id,
                'name' => $topMember->name,
                'total_spent' => (int) $topMember->loyalty_total_spent,
            ] : null,
        ];
    }

    private function memberQuery(): Builder
    {
        return Customer::query()
            ->where(function (Builder $query) {
                $query
                    ->where('is_loyalty_member', true)
                    ->orWhereNotNull('member_code');
            });
    }
}
