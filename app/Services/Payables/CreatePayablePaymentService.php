<?php

declare(strict_types=1);

namespace App\Services\Payables;

use App\Models\Payable;
use App\Models\PayablePayment;
use Illuminate\Support\Facades\DB;

class CreatePayablePaymentService
{
    public function execute(Payable $payable, array $data, int $userId): bool
    {
        if ($data['amount'] > $payable->remaining) {
            return false;
        }

        DB::transaction(function () use ($data, $payable, $userId) {
            PayablePayment::create([
                'payable_id' => $payable->id,
                'paid_at' => $data['paid_at'],
                'amount' => $data['amount'],
                'method' => $data['method'],
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'note' => $data['note'] ?? null,
                'user_id' => $userId,
            ]);

            $payable->paid = ($payable->paid ?? 0) + $data['amount'];
            $remaining = max(0, ($payable->total ?? 0) - ($payable->paid ?? 0));
            $payable->status = $remaining <= 0 ? 'paid' : 'partial';

            if ($payable->status !== 'paid' && $payable->due_date && now()->gt($payable->due_date)) {
                $payable->status = 'overdue';
            }

            $payable->save();
        });

        return true;
    }
}
