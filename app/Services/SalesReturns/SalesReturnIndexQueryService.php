<?php

declare(strict_types=1);

namespace App\Services\SalesReturns;

use App\Models\SalesReturn;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class SalesReturnIndexQueryService
{
    public function __construct(
        private readonly SalesReturnGuardService $guard
    ) {}

    public function execute(array $filters, User $user): array
    {
        $this->guard->ensureTablesExist();

        $salesReturns = SalesReturn::query()
            ->with(['transaction:id,invoice,payment_method,payment_status', 'customer:id,name', 'cashier:id,name'])
            ->when(! $user->isSuperAdmin(), function (Builder $query) use ($user) {
                $query->whereHas('transaction', function (Builder $builder) use ($user) {
                    $builder->where('cashier_id', $user->id);
                });
            })
            ->when($filters['code'], fn (Builder $query, $code) => $query->where('code', 'like', '%'.$code.'%'))
            ->when($filters['invoice'], function (Builder $query, $invoice) {
                $query->whereHas('transaction', fn (Builder $builder) => $builder->where('invoice', 'like', '%'.$invoice.'%'));
            })
            ->when($filters['date_from'], fn (Builder $query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'], fn (Builder $query, $date) => $query->whereDate('created_at', '<=', $date))
            ->when($filters['return_type'], fn (Builder $query, $returnType) => $query->where('return_type', $returnType))
            ->withCount('items')
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return [
            'salesReturns' => $salesReturns,
            'filters' => $filters,
        ];
    }
}
