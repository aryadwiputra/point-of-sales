<?php

declare(strict_types=1);

namespace App\Services\Payables;

use App\Models\Payable;
use App\Models\Supplier;

class PayableSupplierStatementQueryService
{
    public function execute(int $supplierId): array
    {
        $supplier = Supplier::findOrFail($supplierId);

        $payables = Payable::where('supplier_id', $supplier->id)
            ->withSum('payments as total_paid', 'amount')
            ->orderBy('due_date')
            ->get();

        $payables->transform(function (Payable $item) {
            if ($item->status !== 'paid' && $item->due_date && now()->gt($item->due_date)) {
                $item->status = 'overdue';
            }

            $daysOverdue = $item->status === 'overdue' && $item->due_date
                ? now()->diffInDays($item->due_date)
                : 0;

            $item->aging_bucket = match (true) {
                $item->status === 'paid' => 'paid',
                $daysOverdue <= 0 => 'current',
                $daysOverdue <= 30 => '0-30',
                $daysOverdue <= 60 => '31-60',
                $daysOverdue <= 90 => '61-90',
                default => '90+',
            };

            return $item;
        });

        $agingSummary = $payables->groupBy('aging_bucket')->map(function ($group, $bucket) {
            return [
                'bucket' => $bucket,
                'count' => $group->count(),
                'total' => $group->sum('total'),
                'paid' => $group->sum('total_paid'),
                'remaining' => $group->sum(fn ($p) => max(0, $p->total - $p->total_paid)),
            ];
        })->values();

        return [
            'supplier' => $supplier,
            'payables' => $payables,
            'aging_summary' => $agingSummary,
            'total_outstanding' => $payables->where('status', '!=', 'paid')->sum(fn ($p) => max(0, $p->total - $p->total_paid)),
        ];
    }
}
