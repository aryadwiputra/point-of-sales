<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Customer;
use App\Models\Profit;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;

class ProfitReportQueryService
{
    public function __construct(
        private readonly TransactionReportFilterService $filterService
    ) {}

    public function execute(array $filters): array
    {
        $baseQuery = $this->filterService->apply(
            Transaction::query()
                ->with(['cashier:id,name', 'customer:id,name'])
                ->withSum('profits as total_profit', 'total')
                ->withSum('details as total_items', 'qty'),
            $filters
        )->orderByDesc('created_at');

        $transactions = (clone $baseQuery)
            ->paginate(10)
            ->withQueryString();

        $transactionIds = (clone $baseQuery)->pluck('id');

        $profitTotal = $transactionIds->isNotEmpty()
            ? Profit::whereIn('transaction_id', $transactionIds)->sum('total')
            : 0;

        $revenueTotal = (clone $baseQuery)->sum('grand_total');

        $ordersCount = (clone $baseQuery)->count();

        $itemsSold = $transactionIds->isNotEmpty()
            ? TransactionDetail::whereIn('transaction_id', $transactionIds)->sum('qty')
            : 0;

        $bestTransaction = (clone $baseQuery)->get()->sortByDesc('total_profit')->first();

        return [
            'transactions' => $transactions,
            'summary' => [
                'profit_total' => (int) $profitTotal,
                'revenue_total' => (int) $revenueTotal,
                'orders_count' => (int) $ordersCount,
                'items_sold' => (int) $itemsSold,
                'average_profit' => $ordersCount > 0 ? (int) round($profitTotal / $ordersCount) : 0,
                'margin' => $revenueTotal > 0 ? round(($profitTotal / $revenueTotal) * 100, 2) : 0,
                'best_invoice' => $bestTransaction?->invoice,
                'best_profit' => (int) ($bestTransaction?->total_profit ?? 0),
            ],
            'filters' => $filters,
            'cashiers' => User::select('id', 'name')->orderBy('name')->get(),
            'customers' => Customer::select('id', 'name')->orderBy('name')->get(),
        ];
    }
}
