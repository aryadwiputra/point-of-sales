<?php

declare(strict_types=1);

namespace App\Services\CustomerVouchers;

use App\Models\CustomerVoucher;
use App\Services\AuditLogService;

class UpdateCustomerVoucherService
{
    public function __construct(
        private readonly CustomerVoucherPayloadService $payloadService,
        private readonly AuditLogService $auditLogService
    ) {}

    public function execute(CustomerVoucher $voucher, array $data): CustomerVoucher
    {
        $before = $this->payloadService->auditPayload($voucher);

        $voucher->update([
            ...$data,
            'code' => ($data['code'] ?? null) ?: $voucher->code,
        ]);

        $this->auditLogService->log(
            event: 'customer_voucher.updated',
            module: 'customer_vouchers',
            auditable: $voucher,
            description: 'Voucher customer diperbarui.',
            before: $before,
            after: $this->payloadService->auditPayload($voucher->fresh('customer'))
        );

        return $voucher;
    }
}
