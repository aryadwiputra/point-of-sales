<?php

declare(strict_types=1);

namespace App\Services\BankAccounts;

use App\Models\BankAccount;
use App\Services\AuditLogService;

class UpdateBankAccountOrderService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly BankAccountPayloadService $payloadService
    ) {}

    public function execute(array $order): void
    {
        $beforeOrder = $this->payloadService->orderPayload();

        foreach ($order as $index => $id) {
            BankAccount::query()
                ->where('id', $id)
                ->update(['sort_order' => $index]);
        }

        $this->auditLogService->log(
            event: 'bank_account.reordered',
            module: 'bank_accounts',
            auditable: ['target_label' => 'Bank Accounts'],
            description: 'Urutan rekening bank diperbarui.',
            before: ['order' => $beforeOrder],
            after: ['order' => $this->payloadService->orderPayload()]
        );
    }
}
