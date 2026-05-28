<?php

declare(strict_types=1);

namespace App\Services\BankAccounts;

use App\Models\BankAccount;
use App\Services\AuditLogService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UpdateBankAccountService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly BankAccountPayloadService $payloadService
    ) {}

    public function execute(BankAccount $bankAccount, array $data): BankAccount
    {
        $before = $this->payloadService->auditPayload($bankAccount);

        if (($data['logo'] ?? null) instanceof UploadedFile) {
            if ($bankAccount->logo) {
                Storage::disk('public')->delete($bankAccount->logo);
            }

            $data['logo'] = $data['logo']->store('bank-logos', 'public');
        } else {
            unset($data['logo']);
        }

        $bankAccount->update($data);
        $bankAccount = $bankAccount->fresh();

        $this->auditLogService->log(
            event: 'bank_account.updated',
            module: 'bank_accounts',
            auditable: $bankAccount,
            description: 'Rekening bank diperbarui.',
            before: $before,
            after: $this->payloadService->auditPayload($bankAccount)
        );

        return $bankAccount;
    }
}
