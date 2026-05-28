<?php

declare(strict_types=1);

namespace App\Services\Receivables;

use App\Models\BankAccount;
use App\Models\Receivable;

class ReceivableShowQueryService
{
    public function execute(Receivable $receivable): array
    {
        $receivable->load([
            'customer:id,name,no_telp',
            'transaction',
            'payments' => function ($query) {
                $query->orderByDesc('paid_at')->with(['bankAccount:id,bank_name,account_number,account_name,logo', 'user:id,name']);
            },
        ]);

        return [
            'receivable' => $receivable,
            'bankAccounts' => BankAccount::active()->ordered()->get(['id', 'bank_name', 'account_number', 'account_name', 'logo']),
        ];
    }
}
