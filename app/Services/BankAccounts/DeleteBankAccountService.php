<?php

declare(strict_types=1);

namespace App\Services\BankAccounts;

use App\Models\BankAccount;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Storage;

class DeleteBankAccountService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly BankAccountPayloadService $payloadService
    ) {}

    public function execute(BankAccount $bankAccount): bool
    {
        if ($bankAccount->transactions()->exists()) {
            return false;
        }

        $before = $this->payloadService->auditPayload($bankAccount);

        if ($bankAccount->logo) {
            Storage::disk('public')->delete($bankAccount->logo);
        }

        $bankAccount->delete();

        $this->auditLogService->log(
            event: 'bank_account.deleted',
            module: 'bank_accounts',
            auditable: $bankAccount,
            description: 'Rekening bank dihapus.',
            before: $before
        );

        return true;
    }
}
