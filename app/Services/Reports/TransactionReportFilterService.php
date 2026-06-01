<?php

declare(strict_types=1);

namespace App\Services\Reports;

use Illuminate\Database\Eloquent\Builder;

class TransactionReportFilterService
{
    public function apply(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['invoice'] ?? null, fn (Builder $query, $invoice) => $query->where('invoice', 'like', '%'.$invoice.'%'))
            ->when($filters['cashier_id'] ?? null, fn (Builder $query, $cashierId) => $query->where('cashier_id', $cashierId))
            ->when($filters['customer_id'] ?? null, fn (Builder $query, $customerId) => $query->where('customer_id', $customerId))
            ->when($filters['start_date'] ?? null, fn (Builder $query, $startDate) => $query->whereDate('created_at', '>=', $startDate))
            ->when($filters['end_date'] ?? null, fn (Builder $query, $endDate) => $query->whereDate('created_at', '<=', $endDate));
    }
}
