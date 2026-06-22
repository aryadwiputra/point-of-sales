<?php

declare(strict_types=1);

namespace App\Services\Payments\Webhooks;

use App\Models\Transaction;
use App\Services\AuditLogService;

class PaymentWebhookAuditService
{
    public function __construct(
        private readonly AuditLogService $auditLogService
    ) {}

    public function logTransactionUpdate(
        Transaction $transaction,
        string $provider,
        string $beforeStatus,
        ?string $beforeReference
    ): void {
        $this->auditLogService->log(
            event: 'transaction.payment_webhook_updated',
            module: 'transactions',
            auditable: $transaction,
            description: "Status pembayaran invoice {$transaction->invoice} diperbarui oleh webhook {$provider}.",
            before: [
                'payment_status' => $beforeStatus,
                'payment_reference' => $beforeReference,
            ],
            after: [
                'payment_status' => $transaction->payment_status,
                'payment_reference' => $transaction->payment_reference,
            ],
            meta: [
                'provider' => $provider,
                'invoice' => $transaction->invoice,
            ],
        );
    }
}
