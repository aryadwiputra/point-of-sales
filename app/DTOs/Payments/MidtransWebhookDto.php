<?php

declare(strict_types=1);

namespace App\DTOs\Payments;

use App\Http\Requests\PaymentWebhook\MidtransWebhookRequest;

class MidtransWebhookDto
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $statusCode,
        public readonly string $grossAmount,
        public readonly string $signatureKey,
        public readonly string $transactionStatus,
        public readonly ?string $fraudStatus,
        public readonly ?string $transactionId,
    ) {}

    public static function fromRequest(MidtransWebhookRequest $request): self
    {
        return new self(
            orderId: $request->validated('order_id'),
            statusCode: $request->validated('status_code'),
            grossAmount: $request->validated('gross_amount'),
            signatureKey: $request->validated('signature_key'),
            transactionStatus: $request->validated('transaction_status'),
            fraudStatus: $request->validated('fraud_status'),
            transactionId: $request->validated('transaction_id'),
        );
    }
}
