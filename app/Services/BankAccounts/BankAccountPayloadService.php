<?php

declare(strict_types=1);

namespace App\Services\BankAccounts;

use App\Models\BankAccount;
use App\Services\AuditLogService;

class BankAccountPayloadService
{
    public function __construct(
        private readonly AuditLogService $auditLogService
    ) {}

    public function auditPayload(BankAccount $bankAccount): array
    {
        return [
            'bank_name' => $bankAccount->bank_name,
            'account_number_masked' => $this->auditLogService->maskAccountNumber($bankAccount->account_number),
            'account_name' => $bankAccount->account_name,
            'is_active' => (bool) $bankAccount->is_active,
            'sort_order' => (int) $bankAccount->sort_order,
        ];
    }

    public function orderPayload(): array
    {
        return BankAccount::query()
            ->ordered()
            ->get(['id', 'bank_name', 'sort_order'])
            ->map(fn (BankAccount $account) => [
                'id' => $account->id,
                'bank_name' => $account->bank_name,
                'sort_order' => (int) $account->sort_order,
            ])
            ->all();
    }
}
