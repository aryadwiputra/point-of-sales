<?php

declare(strict_types=1);

namespace App\Services\BankAccounts;

use App\Models\BankAccount;
use App\Services\AuditLogService;
use Illuminate\Http\UploadedFile;

class CreateBankAccountService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly BankAccountPayloadService $payloadService
    ) {}

    public function execute(array $data): BankAccount
    {
        if (($data['logo'] ?? null) instanceof UploadedFile) {
            $data['logo'] = $data['logo']->store('bank-logos', 'public');
        } else {
            unset($data['logo']);
        }

        $data['sort_order'] = ((int) BankAccount::query()->max('sort_order')) + 1;

        $bankAccount = BankAccount::create($data);

        $this->auditLogService->log(
            event: 'bank_account.created',
            module: 'bank_accounts',
            auditable: $bankAccount,
            description: 'Rekening bank ditambahkan.',
            after: $this->payloadService->auditPayload($bankAccount)
        );

        return $bankAccount;
    }
}
