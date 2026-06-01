<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Transaction;
use Illuminate\Database\Eloquent\Model;

class TransactionRepository extends BaseRepository
{
    protected function model(): Model
    {
        return new Transaction;
    }

    public function findByInvoiceForUpdate(string $invoice): ?Transaction
    {
        return $this->query()
            ->where('invoice', $invoice)
            ->lockForUpdate()
            ->first();
    }

    public function updatePaymentState(
        Transaction $transaction,
        string $paymentStatus,
        ?string $paymentReference
    ): Transaction {
        $transaction->update([
            'payment_status' => $paymentStatus,
            'payment_reference' => $paymentReference ?: $transaction->payment_reference,
        ]);

        return $transaction->fresh();
    }
}
