<?php

declare(strict_types=1);

namespace App\Services\Receivables;

use App\Models\Receivable;
use App\Models\ReceivablePayment;
use Illuminate\Support\Facades\DB;

class CreateReceivablePaymentService
{
    public function execute(Receivable $receivable, array $data, int $userId): bool
    {
        if ($data['amount'] > $receivable->remaining) {
            return false;
        }

        DB::transaction(function () use ($data, $receivable, $userId) {
            ReceivablePayment::create([
                'receivable_id' => $receivable->id,
                'paid_at' => $data['paid_at'],
                'amount' => $data['amount'],
                'method' => $data['method'],
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'note' => $data['note'] ?? null,
                'user_id' => $userId,
            ]);

            $receivable->paid = ($receivable->paid ?? 0) + $data['amount'];
            $remaining = max(0, ($receivable->total ?? 0) - ($receivable->paid ?? 0));
            $receivable->status = $remaining <= 0 ? 'paid' : 'partial';

            if ($receivable->status !== 'paid' && $receivable->due_date && now()->gt($receivable->due_date)) {
                $receivable->status = 'overdue';
            }

            $receivable->save();

            if ($receivable->transaction) {
                $receivable->transaction->update([
                    'payment_status' => $receivable->status === 'paid' ? 'paid' : 'unpaid',
                ]);
            }
        });

        return true;
    }
}
