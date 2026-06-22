<?php

declare(strict_types=1);

namespace App\DTOs\Payments;

use App\Http\Requests\PaymentWebhook\XenditWebhookRequest;

class XenditWebhookDto
{
    public function __construct(
        public readonly string $externalId,
        public readonly string $status,
        public readonly string $paymentId,
        public readonly ?string $callbackToken,
    ) {}

    public static function fromRequest(XenditWebhookRequest $request): self
    {
        return new self(
            externalId: $request->validated('external_id'),
            status: $request->validated('status'),
            paymentId: $request->validated('id'),
            callbackToken: $request->header('X-CALLBACK-TOKEN'),
        );
    }
}
