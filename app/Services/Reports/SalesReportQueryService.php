<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Customer;
use App\Models\Profit;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;

class SalesReportQueryService
{
    public function __construct(
        private readonly TransactionReportFilterService $filterService
    ) {}

    public function execute(array $filters): array
    {
        $baseListQuery = $this->filterService->apply(
            Transaction::query()
                ->with(['cashier:id,name', 'customer:id,name'])
                ->withSum('details as total_items', 'qty')
                ->withSum('profits as total_profit', 'total'),
            $filters
        )->orderByDesc('created_at');

        $transactions = (clone $baseListQuery)
            ->paginate(10)
            ->withQueryString();

        $aggregateQuery = $this->filterService->apply(Transaction::query(), $filters);

        $totals = (clone $aggregateQuery)
            ->selectRaw('
                COUNT(*) as orders_count,
                COALESCE(SUM(grand_total), 0) as revenue_total,
                COALESCE(SUM(discount), 0) as discount_total
            ')
            ->first();

        $transactionIds = (clone $aggregateQuery)->pluck('id');

        $itemsSold = $transactionIds->isNotEmpty()
            ? TransactionDetail::whereIn('transaction_id', $transactionIds)->sum('qty')
            : 0;

        $profitTotal = $transactionIds->isNotEmpty()
            ? Profit::whereIn('transaction_id', $transactionIds)->sum('total')
            : 0;

        return [
            'transactions' => $transactions,
            'summary' => [
                'orders_count' => (int) ($totals->orders_count ?? 0),
                'revenue_total' => (int) ($totals->revenue_total ?? 0),
                'discount_total' => (int) ($totals->discount_total ?? 0),
                'items_sold' => (int) $itemsSold,
                'profit_total' => (int) $profitTotal,
                'average_order' => ($totals->orders_count ?? 0) > 0
                    ? (int) round($totals->revenue_total / $totals->orders_count)
                    : 0,
            ],
            'filters' => $filters,
            'cashiers' => User::select('id', 'name')->orderBy('name')->get(),
            'customers' => Customer::select('id', 'name')->orderBy('name')->get(),
        ];
    }
}
