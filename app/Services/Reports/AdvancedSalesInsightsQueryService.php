<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Category;
use App\Models\Customer;
use App\Models\User;
use App\Repositories\Reports\AdvancedSalesInsightsRepository;
use Illuminate\Support\Facades\DB;

class AdvancedSalesInsightsQueryService
{
    public function __construct(
        private readonly AdvancedSalesInsightsRepository $repository,
        private readonly SalesPerformanceInsightsQueryService $salesPerformance,
        private readonly RepeatCustomerInsightsQueryService $repeatCustomers,
        private readonly StockCoverageInsightsQueryService $stockCoverage,
        private readonly OperationalInsightsQueryService $operations
    ) {}

    public function execute(array $filters): array
    {
        $transactionQuery = $this->repository->transactionQuery($filters);
        $transactionIds = (clone $transactionQuery)->pluck('id');
        $transactionCount = $transactionIds->count();

        $summaryRaw = (clone $transactionQuery)
            ->selectRaw('COUNT(*) as orders_count, COALESCE(SUM(grand_total), 0) as revenue_total, COALESCE(SUM(discount), 0) as manual_discount_total')
            ->first();

        $itemsSold = $transactionIds->isNotEmpty()
            ? DB::table('transaction_details')
                ->whereIn('transaction_id', $transactionIds)
                ->sum('qty')
            : 0;

        $profitTotal = $transactionIds->isNotEmpty()
            ? DB::table('profits')
                ->whereIn('transaction_id', $transactionIds)
                ->sum('total')
            : 0;

        $operations = $this->operations->execute($filters);

        return [
            'filters' => $filters,
            'cashiers' => User::select('id', 'name')->orderBy('name')->get(),
            'customers' => Customer::select('id', 'name')->orderBy('name')->get(),
            'categories' => Category::select('id', 'name')->orderBy('name')->get(),
            'summary' => [
                'orders_count' => (int) ($summaryRaw->orders_count ?? 0),
                'revenue_total' => (int) ($summaryRaw->revenue_total ?? 0),
                'manual_discount_total' => (int) ($summaryRaw->manual_discount_total ?? 0),
                'items_sold' => (int) $itemsSold,
                'profit_total' => (int) $profitTotal,
                'average_order' => $transactionCount > 0
                    ? (int) round(($summaryRaw->revenue_total ?? 0) / $transactionCount)
                    : 0,
            ],
            'salesByHour' => $this->salesPerformance->salesByHour($filters),
            'salesByDay' => $this->salesPerformance->salesByDay($filters),
            'topSellingProducts' => $this->salesPerformance->topSellingProducts($filters),
            'lowPerformingProducts' => $this->salesPerformance->lowPerformingProducts($filters),
            'marginByProduct' => $this->salesPerformance->marginByProduct($filters),
            'marginByCategory' => $this->salesPerformance->marginByCategory($filters),
            'cashierPerformance' => $this->salesPerformance->cashierPerformance($filters),
            'repeatCustomerMetrics' => $this->repeatCustomers->execute($filters),
            'stockCoverage' => $this->stockCoverage->execute($filters),
            'promoMonitor' => $operations['promoMonitor'],
            'loyaltyPerformance' => $operations['loyaltyPerformance'],
            'crmOperations' => $operations['crmOperations'],
        ];
    }
}
