<?php

declare(strict_types=1);

namespace App\Services\CustomerVouchers;

use App\Models\CustomerVoucher;
use App\Services\AuditLogService;

class DeleteCustomerVoucherService
{
    public function __construct(
        private readonly CustomerVoucherPayloadService $payloadService,
        private readonly AuditLogService $auditLogService
    ) {}

    public function execute(CustomerVoucher $voucher): void
    {
        $before = $this->payloadService->auditPayload($voucher);

        $voucher->delete();

        $this->auditLogService->log(
            event: 'customer_voucher.deleted',
            module: 'customer_vouchers',
            auditable: $voucher,
            description: 'Voucher customer dihapus.',
            before: $before
        );
    }
}
