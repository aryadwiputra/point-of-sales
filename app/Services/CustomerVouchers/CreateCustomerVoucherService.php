<?php

declare(strict_types=1);

namespace App\Services\CustomerVouchers;

use App\Models\CustomerVoucher;
use App\Services\AuditLogService;

class CreateCustomerVoucherService
{
    public function __construct(
        private readonly CustomerVoucherCodeService $codeService,
        private readonly CustomerVoucherPayloadService $payloadService,
        private readonly AuditLogService $auditLogService
    ) {}

    public function execute(array $data, ?int $userId): CustomerVoucher
    {
        $voucher = CustomerVoucher::query()->create([
            ...$data,
            'created_by' => $userId,
            'code' => ($data['code'] ?? null) ?: $this->codeService->generate(),
        ]);

        $this->auditLogService->log(
            event: 'customer_voucher.created',
            module: 'customer_vouchers',
            auditable: $voucher,
            description: 'Voucher customer dibuat.',
            after: $this->payloadService->auditPayload($voucher->fresh('customer'))
        );

        return $voucher;
    }
}
