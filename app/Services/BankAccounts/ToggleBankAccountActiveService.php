<?php

declare(strict_types=1);

namespace App\Services\BankAccounts;

use App\Models\BankAccount;
use App\Services\AuditLogService;

class ToggleBankAccountActiveService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly BankAccountPayloadService $payloadService
    ) {}

    public function execute(BankAccount $bankAccount): BankAccount
    {
        $before = $this->payloadService->auditPayload($bankAccount);

        $bankAccount->update([
            'is_active' => ! $bankAccount->is_active,
        ]);

        $bankAccount = $bankAccount->fresh();
        $status = $bankAccount->is_active ? 'diaktifkan' : 'dinonaktifkan';

        $this->auditLogService->log(
            event: 'bank_account.toggled',
            module: 'bank_accounts',
            auditable: $bankAccount,
            description: "Status rekening bank {$status}.",
            before: $before,
            after: $this->payloadService->auditPayload($bankAccount)
        );

        return $bankAccount;
    }
}
