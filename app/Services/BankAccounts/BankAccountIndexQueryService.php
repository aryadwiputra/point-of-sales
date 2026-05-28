<?php

declare(strict_types=1);

namespace App\Services\BankAccounts;

use App\Models\BankAccount;

class BankAccountIndexQueryService
{
    public function execute(): array
    {
        return [
            'bankAccounts' => BankAccount::query()->ordered()->get(),
        ];
    }
}
