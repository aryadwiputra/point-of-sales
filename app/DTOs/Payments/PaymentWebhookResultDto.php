<?php

declare(strict_types=1);

namespace App\DTOs\Payments;

class PaymentWebhookResultDto
{
    private function __construct(
        public readonly int $httpStatus,
        public readonly string $status,
        public readonly ?string $message = null,
    ) {}

    public static function success(): self
    {
        return new self(200, 'success');
    }

    public static function error(string $message, int $httpStatus): self
    {
        return new self($httpStatus, 'error', $message);
    }

    public function toArray(): array
    {
        return array_filter([
            'status' => $this->status,
            'message' => $this->message,
        ], fn ($value) => $value !== null);
    }
}
