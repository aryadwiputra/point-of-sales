<?php

declare(strict_types=1);

namespace App\Services\Transactions;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class TransactionHistoryQueryService
{
    public function execute(array $filters, User $user): array
    {
        $salesReturnTablesReady = Schema::hasTable('sales_returns') && Schema::hasTable('sales_return_items');

        $query = Transaction::query()
            ->with([
                'cashier:id,name',
                'cashierShift:id,opened_at,status',
                'customer:id,name',
                'receivable',
            ])
            ->withSum('details as total_items', 'qty')
            ->withSum('profits as total_profit', 'total')
            ->orderByDesc('created_at');

        if ($salesReturnTablesReady) {
            $query->with('details.salesReturnItems.salesReturn:id,status');
        }

        if (! $user->isSuperAdmin()) {
            $query->where('cashier_id', $user->id);
        }

        $query
            ->when($filters['invoice'], function (Builder $builder, $invoice) {
                $builder->where('invoice', 'like', '%'.$invoice.'%');
            })
            ->when($filters['start_date'], function (Builder $builder, $date) {
                $builder->whereDate('created_at', '>=', $date);
            })
            ->when($filters['end_date'], function (Builder $builder, $date) {
                $builder->whereDate('created_at', '<=', $date);
            });

        $transactions = $query->paginate(10)->withQueryString();
        $transactions->through(function (Transaction $transaction) use ($salesReturnTablesReady) {
            $canCreateSalesReturn = false;

            if ($salesReturnTablesReady) {
                $allReturned = true;

                foreach ($transaction->details as $detail) {
                    $returnedQty = (int) $detail->salesReturnItems
                        ->filter(fn ($item) => $item->salesReturn?->status === 'completed')
                        ->sum('qty_return');

                    if ($returnedQty < (int) $detail->qty) {
                        $allReturned = false;
                        break;
                    }
                }

                $canCreateSalesReturn = $transaction->details->isNotEmpty() && ! $allReturned;
            }

            return [
                ...$transaction->toArray(),
                'can_create_sales_return' => $canCreateSalesReturn,
            ];
        });

        return [
            'transactions' => $transactions,
            'filters' => $filters,
            'salesReturnFeatureReady' => $salesReturnTablesReady,
        ];
    }
}
