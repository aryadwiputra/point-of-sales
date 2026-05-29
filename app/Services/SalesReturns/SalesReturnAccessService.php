<?php

declare(strict_types=1);

namespace App\Services\SalesReturns;

use App\Models\SalesReturn;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class SalesReturnAccessService
{
    public function resolveAccessibleTransaction(User $user, int $transactionId): Transaction
    {
        return Transaction::query()
            ->with([
                'cashier:id,name',
                'customer:id,name',
                'receivable',
                'details.product:id,title,barcode,sku,buy_price',
                'details.salesReturnItems.salesReturn:id,status',
            ])
            ->when(! $user->isSuperAdmin(), fn (Builder $query) => $query->where('cashier_id', $user->id))
            ->findOrFail($transactionId);
    }

    public function resolveAccessibleSalesReturn(User $user, int $salesReturnId): SalesReturn
    {
        return SalesReturn::query()
            ->with([
                'customer:id,name',
                'cashier:id,name',
                'transaction.cashier:id,name',
                'transaction.customer:id,name',
                'transaction.receivable',
                'transaction.details.product:id,title,barcode,sku,buy_price',
                'transaction.details.salesReturnItems.salesReturn:id,status',
                'items.product:id,title,barcode,sku,buy_price',
                'items.transactionDetail:id,transaction_id,product_id,qty,price',
            ])
            ->when(! $user->isSuperAdmin(), function (Builder $query) use ($user) {
                $query->whereHas('transaction', fn (Builder $builder) => $builder->where('cashier_id', $user->id));
            })
            ->findOrFail($salesReturnId);
    }
}
