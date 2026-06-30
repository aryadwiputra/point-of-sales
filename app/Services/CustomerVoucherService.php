<?php

namespace App\Services;

use App\Data\CustomerVoucherData;
use App\Models\CustomerVoucher;
use Illuminate\Support\Str;

class CustomerVoucherService
{
    public function __construct(
        private readonly AuditLogService $auditLogService
    ) {}

    public function createVoucher(CustomerVoucherData $data, ?int $userId = null): CustomerVoucher
    {
        $voucher = CustomerVoucher::create([
            'customer_id' => $data->customer_id,
            'name' => $data->name,
            'discount_type' => $data->discount_type,
            'discount_value' => $data->discount_value,
            'minimum_order' => $data->minimum_order,
            'is_active' => $data->is_active ?? true,
            'is_used' => $data->is_used ?? false,
            'starts_at' => $data->starts_at,
            'expires_at' => $data->expires_at,
            'used_at' => $data->used_at,
            'used_transaction_id' => $data->used_transaction_id,
            'notes' => $data->notes,
            'created_by' => $userId,
            'code' => $data->code ?? $this->generateVoucherCode(),
        ]);

        $this->auditLogService->log(
            event: 'customer_voucher.created',
            module: 'customer_vouchers',
            auditable: $voucher,
            description: 'Voucher customer dibuat.',
            after: $this->auditPayload($voucher->fresh('customer'))
        );

        return $voucher;
    }

    public function updateVoucher(CustomerVoucher $voucher, CustomerVoucherData $data): CustomerVoucher
    {
        $before = $this->auditPayload($voucher);

        $voucher->update([
            'customer_id' => $data->customer_id,
            'name' => $data->name,
            'discount_type' => $data->discount_type,
            'discount_value' => $data->discount_value,
            'minimum_order' => $data->minimum_order,
            'is_active' => $data->is_active ?? $voucher->is_active,
            'is_used' => $data->is_used ?? $voucher->is_used,
            'starts_at' => $data->starts_at,
            'expires_at' => $data->expires_at,
            'used_at' => $data->used_at,
            'used_transaction_id' => $data->used_transaction_id,
            'notes' => $data->notes,
            'code' => $data->code ?? $voucher->code,
        ]);

        $this->auditLogService->log(
            event: 'customer_voucher.updated',
            module: 'customer_vouchers',
            auditable: $voucher,
            description: 'Voucher customer diperbarui.',
            before: $before,
            after: $this->auditPayload($voucher->fresh('customer'))
        );

        return $voucher;
    }

    public function deleteVoucher(CustomerVoucher $voucher): void
    {
        $before = $this->auditPayload($voucher);
        $voucher->delete();

        $this->auditLogService->log(
            event: 'customer_voucher.deleted',
            module: 'customer_vouchers',
            auditable: $voucher,
            description: 'Voucher customer dihapus.',
            before: $before
        );
    }

    private function auditPayload(CustomerVoucher $voucher): array
    {
        return [
            'customer_id' => $voucher->customer_id,
            'code' => $voucher->code,
            'name' => $voucher->name,
            'discount_type' => $voucher->discount_type,
            'discount_value' => (float) $voucher->discount_value,
            'minimum_order' => (int) $voucher->minimum_order,
            'is_active' => (bool) $voucher->is_active,
            'is_used' => (bool) $voucher->is_used,
            'starts_at' => optional($voucher->starts_at)?->toIso8601String(),
            'expires_at' => optional($voucher->expires_at)?->toIso8601String(),
            'used_at' => optional($voucher->used_at)?->toIso8601String(),
        ];
    }

    private function generateVoucherCode(): string
    {
        do {
            $code = 'VCR-'.Str::upper(Str::random(8));
        } while (CustomerVoucher::query()->where('code', $code)->exists());

        return $code;
    }
}
