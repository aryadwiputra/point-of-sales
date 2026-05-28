<?php

declare(strict_types=1);

namespace App\Services\Payables;

use App\Models\Payable;
use App\Models\Supplier;

class PayableIndexQueryService
{
    public function execute(array $filters): array
    {
        $query = Payable::with('supplier:id,name')
            ->withSum('payments as total_paid', 'amount')
            ->orderByDesc('created_at');

        $query->when($filters['status'], function ($q, $status) {
            $q->where('status', $status);
        })->when($filters['supplier'], function ($q, $supplier) {
            $q->where('supplier_id', $supplier);
        })->when($filters['invoice'], function ($q, $invoice) {
            $q->where('document_number', 'like', '%'.$invoice.'%');
        })->when($filters['due_from'], function ($q, $date) {
            $q->whereDate('due_date', '>=', $date);
        })->when($filters['due_to'], function ($q, $date) {
            $q->whereDate('due_date', '<=', $date);
        });

        $payables = $query->paginate(10)->withQueryString();
        $payables->getCollection()->transform(function (Payable $item) {
            if ($item->status !== 'paid' && $item->due_date && now()->gt($item->due_date)) {
                $item->status = 'overdue';
            }

            return $item;
        });

        return [
            'payables' => $payables,
            'filters' => $filters,
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
        ];
    }
}
