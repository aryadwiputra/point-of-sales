<?php

declare(strict_types=1);

namespace App\Services\Payables;

use App\Models\BankAccount;
use App\Models\Payable;

class PayableShowQueryService
{
    public function execute(Payable $payable): array
    {
        $payable->load([
            'supplier:id,name,phone,email,address',
            'purchaseOrder:id,document_number,status',
            'payments' => function ($query) {
                $query->orderByDesc('paid_at')->with(['bankAccount:id,bank_name,account_number,account_name,logo', 'user:id,name']);
            },
        ]);

        return [
            'payable' => $payable,
            'bankAccounts' => BankAccount::active()->ordered()->get(['id', 'bank_name', 'account_number', 'account_name', 'logo']),
        ];
    }
}
