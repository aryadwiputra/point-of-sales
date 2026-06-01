<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Product;
use App\Repositories\Reports\AdvancedSalesInsightsRepository;
use Illuminate\Support\Carbon;

class StockCoverageInsightsQueryService
{
    public function __construct(
        private readonly AdvancedSalesInsightsRepository $repository
    ) {}

    public function execute(array $filters): array
    {
        $windowDays = $this->salesWindowDays($filters);
        $soldBaseQuantity = $this->repository->soldBaseQuantityExpression();

        $salesSubquery = $this->repository->detailMetricsQuery($filters)
            ->selectRaw("
                td.product_id,
                SUM({$soldBaseQuantity}) as qty_sold,
                SUM(td.price) as revenue_total,
                MAX(t.created_at) as last_sold_at
            ")
            ->groupBy('td.product_id');

        $rows = Product::query()
            ->leftJoinSub($salesSubquery, 'sales', fn ($join) => $join->on('sales.product_id', '=', 'products.id'))
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->when($filters['category_id'] ?? null, fn ($query, $categoryId) => $query->where('products.category_id', $categoryId))
            ->where('products.stock', '>', 0)
            ->selectRaw('
                products.id as product_id,
                products.title as product_title,
                products.sku as product_sku,
                categories.name as category_name,
                products.stock as current_stock,
                COALESCE(sales.qty_sold, 0) as qty_sold,
                COALESCE(sales.revenue_total, 0) as revenue_total,
                sales.last_sold_at as last_sold_at
            ')
            ->get()
            ->map(function ($row) use ($windowDays) {
                $qtySold = (int) $row->qty_sold;
                $currentStock = (int) $row->current_stock;
                $averageDailyQty = $windowDays > 0 ? round($qtySold / $windowDays, 2) : 0;
                $coverageDays = $averageDailyQty > 0
                    ? round($currentStock / $averageDailyQty, 1)
                    : null;

                return [
                    'product_id' => (int) $row->product_id,
                    'product_title' => $row->product_title,
                    'product_sku' => $row->product_sku,
                    'category_name' => $row->category_name,
                    'current_stock' => $currentStock,
                    'qty_sold' => $qtySold,
                    'revenue_total' => (int) round((float) $row->revenue_total),
                    'average_daily_qty' => $averageDailyQty,
                    'coverage_days' => $coverageDays,
                    'coverage_status' => $this->coverageStatus($currentStock, $qtySold, $coverageDays),
                    'last_sold_at' => $row->last_sold_at
                        ? Carbon::parse($row->last_sold_at)->toIso8601String()
                        : null,
                ];
            });

        $summaryCounts = [
            'critical' => $rows->where('coverage_status', 'critical')->count(),
            'low' => $rows->where('coverage_status', 'low')->count(),
            'healthy' => $rows->where('coverage_status', 'healthy')->count(),
            'no_movement' => $rows->where('coverage_status', 'no_movement')->count(),
        ];

        $sortedRows = $rows
            ->sort(function (array $first, array $second) {
                $statusPriority = [
                    'critical' => 0,
                    'low' => 1,
                    'healthy' => 2,
                    'no_movement' => 3,
                ];

                $statusComparison = ($statusPriority[$first['coverage_status']] ?? 99)
                    <=> ($statusPriority[$second['coverage_status']] ?? 99);

                if ($statusComparison !== 0) {
                    return $statusComparison;
                }

                return ($first['coverage_days'] ?? INF) <=> ($second['coverage_days'] ?? INF);
            })
            ->take(10)
            ->values();

        return [
            'summary' => [
                'window_days' => $windowDays,
                ...$summaryCounts,
            ],
            'products' => $sortedRows->all(),
        ];
    }

    private function salesWindowDays(array $filters): int
    {
        if (($filters['start_date'] ?? null) && ($filters['end_date'] ?? null)) {
            $start = Carbon::parse($filters['start_date']);
            $end = Carbon::parse($filters['end_date']);

            return max(1, (int) $start->diffInDays($end) + 1);
        }

        $range = $this->repository->transactionQuery($filters)
            ->selectRaw('MIN(transactions.created_at) as min_date, MAX(transactions.created_at) as max_date')
            ->first();

        if (! $range?->min_date || ! $range?->max_date) {
            return 30;
        }

        return max(
            1,
            (int) Carbon::parse($range->min_date)->diffInDays(Carbon::parse($range->max_date)) + 1
        );
    }

    private function coverageStatus(int $currentStock, int $qtySold, ?float $coverageDays): string
    {
        if ($currentStock <= 0) {
            return 'out_of_stock';
        }

        if ($qtySold <= 0 || $coverageDays === null) {
            return 'no_movement';
        }

        if ($coverageDays <= 7) {
            return 'critical';
        }

        if ($coverageDays <= 30) {
            return 'low';
        }

        return 'healthy';
    }
}
