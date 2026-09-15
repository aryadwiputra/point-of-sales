<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Outlet;
use App\Models\Receivable;
use Illuminate\Support\Collection;

class ReceivableService
{
    public function getAgingSummary(?Collection $warehouseIds = null): Collection
    {
        $receivables = $this->scope(Receivable::query(), $warehouseIds)
            ->where('status', '!=', 'paid')
            ->whereNotNull('due_date')
            ->get();

        $buckets = ['current', '0-30', '31-60', '61-90', '90+'];

        return collect($buckets)->map(function ($bucket) use ($receivables) {
            $filtered = $receivables->filter(fn ($r) => $r->aging_bucket === $bucket);

            return [
                'bucket' => $bucket,
                'count' => $filtered->count(),
                'total' => $filtered->sum('total'),
                'paid' => $filtered->sum('paid'),
                'remaining' => $filtered->sum(fn ($r) => max(0, $r->total - $r->paid)),
            ];
        });
    }

    public function scopeQuery($query, Collection $warehouseIds)
    {
        return $this->scope($query, $warehouseIds);
    }

    public function getCustomerStatement(int $customerId, ?Collection $warehouseIds = null): array
    {
        $customer = Customer::findOrFail($customerId);

        $receivables = $this->scope(Receivable::where('customer_id', $customerId), $warehouseIds)
            ->withSum('payments as total_paid', 'amount')
            ->orderBy('due_date')
            ->get();

        $receivables->transform(function ($item) {
            $item->aging_bucket = $item->aging_bucket;

            return $item;
        });

        $totalOutstanding = $receivables
            ->where('status', '!=', 'paid')
            ->sum(fn ($r) => max(0, $r->total - $r->total_paid));

        $totalPaid = $receivables->sum('total_paid');

        return [
            'customer' => $customer,
            'receivables' => $receivables,
            'total_outstanding' => $totalOutstanding,
            'total_paid' => $totalPaid,
        ];
    }

    public function getCollectionRate(?Collection $warehouseIds = null): array
    {
        $query = $this->scope(Receivable::query(), $warehouseIds);
        $totalReceivables = (clone $query)->sum('total');
        $totalPaid = (clone $query)->sum('paid');

        $paidReceivables = (clone $query)->where('status', 'paid')->count();
        $totalReceivablesCount = (clone $query)->count();

        return [
            'total_receivables_amount' => $totalReceivables,
            'total_paid_amount' => $totalPaid,
            'collection_rate' => $totalReceivables > 0
                ? round(($totalPaid / $totalReceivables) * 100, 2)
                : 0,
            'paid_count' => $paidReceivables,
            'total_count' => $totalReceivablesCount,
        ];
    }

    public function getTopCustomersByReceivable(int $limit = 10, ?Collection $warehouseIds = null): Collection
    {
        $scope = fn ($query) => $this->scope($query, $warehouseIds);

        return Customer::withSum([
            'receivables as total_receivable' => fn ($q) => $scope($q)->where('status', '!=', 'paid'),
        ], 'total')
            ->withSum([
                'receivables as total_paid' => fn ($q) => $scope($q),
            ], 'paid')
            ->orderByRaw('COALESCE(total_receivable, 0) DESC')
            ->limit($limit)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'total_receivable' => $c->total_receivable ?? 0,
                'total_paid' => $c->total_paid ?? 0,
                'remaining' => max(0, ($c->total_receivable ?? 0) - ($c->total_paid ?? 0)),
            ])
            ->filter(fn ($c) => $c['remaining'] > 0)
            ->values();
    }

    private function scope($query, ?Collection $warehouseIds)
    {
        if ($warehouseIds === null) {
            return $query;
        }

        return $query->where(function ($builder) use ($warehouseIds) {
            $builder->whereHas('transaction', fn ($transaction) => $transaction->whereIn('warehouse_id', $warehouseIds));

            if ($warehouseIds->isEmpty()) {
                $builder->whereRaw('1 = 0');
            } elseif ($this->singleOutlet()) {
                $builder->orWhereDoesntHave('transaction');
            }
        });
    }

    private function singleOutlet(): bool
    {
        return Outlet::active()->count() <= 1;
    }
}
