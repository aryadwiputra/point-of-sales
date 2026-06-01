<?php

declare(strict_types=1);

namespace App\Repositories\Reports;

use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class AdvancedSalesInsightsRepository
{
    public function transactionQuery(array $filters): Builder
    {
        return $this->applyTransactionFilters(Transaction::query(), $filters);
    }

    public function applyTransactionFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['cashier_id'] ?? null, fn (Builder $query, $cashierId) => $query->where('transactions.cashier_id', $cashierId))
            ->when($filters['customer_id'] ?? null, fn (Builder $query, $customerId) => $query->where('transactions.customer_id', $customerId))
            ->when($filters['start_date'] ?? null, fn (Builder $query, $startDate) => $query->whereDate('transactions.created_at', '>=', $startDate))
            ->when($filters['end_date'] ?? null, fn (Builder $query, $endDate) => $query->whereDate('transactions.created_at', '<=', $endDate))
            ->when($filters['category_id'] ?? null, function (Builder $query, $categoryId) {
                $query->whereHas('details.product', fn (Builder $productQuery) => $productQuery->where('category_id', $categoryId));
            });
    }

    public function detailMetricsQuery(array $filters)
    {
        return DB::table('transaction_details as td')
            ->join('transactions as t', 't.id', '=', 'td.transaction_id')
            ->join('products as p', 'p.id', '=', 'td.product_id')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->when($filters['cashier_id'] ?? null, fn ($query, $cashierId) => $query->where('t.cashier_id', $cashierId))
            ->when($filters['customer_id'] ?? null, fn ($query, $customerId) => $query->where('t.customer_id', $customerId))
            ->when($filters['start_date'] ?? null, fn ($query, $startDate) => $query->whereDate('t.created_at', '>=', $startDate))
            ->when($filters['end_date'] ?? null, fn ($query, $endDate) => $query->whereDate('t.created_at', '<=', $endDate))
            ->when($filters['category_id'] ?? null, fn ($query, $categoryId) => $query->where('p.category_id', $categoryId));
    }

    public function applyDateRangeFilter($query, string $column, array $filters): void
    {
        if ($filters['start_date'] ?? null) {
            $query->whereDate($column, '>=', $filters['start_date']);
        }

        if ($filters['end_date'] ?? null) {
            $query->whereDate($column, '<=', $filters['end_date']);
        }
    }

    public function hourBucketExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "CAST(strftime('%H', created_at) AS INTEGER)",
            'pgsql' => 'CAST(EXTRACT(HOUR FROM created_at) AS INTEGER)',
            default => 'HOUR(created_at)',
        };
    }
}
