<?php

declare(strict_types=1);

namespace App\Services\Receivables;

use App\Models\Receivable;

class ReceivableIndexQueryService
{
    public function execute(array $filters): array
    {
        $query = Receivable::with('customer:id,name')
            ->withSum('payments as total_paid', 'amount')
            ->orderByDesc('created_at');

        $query->when($filters['status'], function ($q, $status) {
            $q->where('status', $status);
        })->when($filters['customer'], function ($q, $customer) {
            $q->where('customer_id', $customer);
        })->when($filters['invoice'], function ($q, $invoice) {
            $q->where('invoice', 'like', '%'.$invoice.'%');
        })->when($filters['due_from'], function ($q, $date) {
            $q->whereDate('due_date', '>=', $date);
        })->when($filters['due_to'], function ($q, $date) {
            $q->whereDate('due_date', '<=', $date);
        });

        $receivables = $query->paginate(10)->withQueryString();
        $receivables->getCollection()->transform(function (Receivable $item) {
            if ($item->status !== 'paid' && $item->due_date && now()->gt($item->due_date)) {
                $item->status = 'overdue';
            }

            return $item;
        });

        return [
            'receivables' => $receivables,
            'filters' => $filters,
        ];
    }
}
