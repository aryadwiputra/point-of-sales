<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Transaction;
use App\Repositories\Reports\AdvancedSalesInsightsRepository;
use Illuminate\Support\Carbon;

class RepeatCustomerInsightsQueryService
{
    public function __construct(
        private readonly AdvancedSalesInsightsRepository $repository
    ) {}

    public function execute(array $filters): array
    {
        $rows = $this->repository->applyTransactionFilters(Transaction::query(), $filters)
            ->whereNotNull('transactions.customer_id')
            ->leftJoin('customers', 'customers.id', '=', 'transactions.customer_id')
            ->selectRaw('
                transactions.customer_id,
                customers.name as customer_name,
                customers.is_loyalty_member as is_loyalty_member,
                customers.loyalty_tier as loyalty_tier,
                COUNT(transactions.id) as orders_count,
                COALESCE(SUM(transactions.grand_total), 0) as revenue_total,
                MAX(transactions.created_at) as last_purchase_at
            ')
            ->groupBy(
                'transactions.customer_id',
                'customers.name',
                'customers.is_loyalty_member',
                'customers.loyalty_tier'
            )
            ->get()
            ->map(fn ($row) => [
                'customer_id' => (int) $row->customer_id,
                'customer_name' => $row->customer_name,
                'is_loyalty_member' => (bool) $row->is_loyalty_member,
                'loyalty_tier' => $row->loyalty_tier,
                'orders_count' => (int) $row->orders_count,
                'revenue_total' => (int) round((float) $row->revenue_total),
                'average_basket' => (int) ($row->orders_count > 0
                    ? round($row->revenue_total / $row->orders_count)
                    : 0),
                'last_purchase_at' => $row->last_purchase_at
                    ? Carbon::parse($row->last_purchase_at)->toIso8601String()
                    : null,
            ]);

        $activeCustomers = $rows->count();
        $repeatCustomers = $rows->filter(fn (array $row) => $row['orders_count'] > 1)->values();
        $newCustomers = $rows->filter(fn (array $row) => $row['orders_count'] === 1)->values();
        $memberRevenue = $rows->where('is_loyalty_member', true)->sum('revenue_total');
        $nonMemberRevenue = $rows->where('is_loyalty_member', false)->sum('revenue_total');
        $repeatRevenue = $repeatCustomers->sum('revenue_total');

        return [
            'summary' => [
                'active_customers' => $activeCustomers,
                'repeat_customers' => $repeatCustomers->count(),
                'new_customers' => $newCustomers->count(),
                'repeat_rate' => $activeCustomers > 0
                    ? round(($repeatCustomers->count() / $activeCustomers) * 100, 2)
                    : 0,
                'repeat_revenue_total' => (int) $repeatRevenue,
                'member_revenue_total' => (int) $memberRevenue,
                'non_member_revenue_total' => (int) $nonMemberRevenue,
                'member_revenue_share' => ($memberRevenue + $nonMemberRevenue) > 0
                    ? round(($memberRevenue / ($memberRevenue + $nonMemberRevenue)) * 100, 2)
                    : 0,
            ],
            'top_customers' => $repeatCustomers
                ->sortByDesc(fn (array $row) => [$row['orders_count'], $row['revenue_total']])
                ->take(10)
                ->values()
                ->all(),
        ];
    }
}
