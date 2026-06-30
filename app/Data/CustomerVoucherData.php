<?php

namespace App\Data;

use App\Models\CustomerVoucher;
use Carbon\Carbon;
use Spatie\LaravelData\Attributes\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class CustomerVoucherData extends Data
{
    public function __construct(
        public int|Optional $id,
        #[Rule(['required', 'integer', 'exists:customers,id'])]
        public int $customer_id,
        #[Rule(['nullable', 'string', 'max:50'])]
        public ?string $code,
        #[Rule(['required', 'string', 'max:255'])]
        public string $name,
        #[Rule(['required', 'in:fixed_amount,percentage'])]
        public string $discount_type,
        #[Rule(['required', 'numeric', 'min:0.01'])]
        public float $discount_value,
        #[Rule(['nullable', 'integer', 'min:0'])]
        public ?int $minimum_order,
        #[Rule(['nullable', 'boolean'])]
        public ?bool $is_active,
        #[Rule(['nullable', 'boolean'])]
        public ?bool $is_used,
        #[Rule(['nullable', 'date'])]
        public ?string $starts_at,
        #[Rule(['nullable', 'date', 'after_or_equal:starts_at'])]
        public ?string $expires_at,
        public ?string $used_at,
        public ?int $used_transaction_id,
        #[Rule(['nullable', 'string', 'max:1000'])]
        public ?string $notes,
        public ?int $created_by,
        public ?array $customer,
        public ?array $creator,
        public ?string $status_label,
    ) {}

    public static function fromModel(CustomerVoucher $voucher): self
    {
        return new self(
            id: $voucher->id,
            customer_id: $voucher->customer_id,
            code: $voucher->code,
            name: $voucher->name,
            discount_type: $voucher->discount_type,
            discount_value: (float) $voucher->discount_value,
            minimum_order: $voucher->minimum_order ? (int) $voucher->minimum_order : null,
            is_active: (bool) $voucher->is_active,
            is_used: (bool) $voucher->is_used,
            starts_at: $voucher->starts_at ? Carbon::parse($voucher->starts_at)->toIso8601String() : null,
            expires_at: $voucher->expires_at ? Carbon::parse($voucher->expires_at)->toIso8601String() : null,
            used_at: $voucher->used_at ? Carbon::parse($voucher->used_at)->toIso8601String() : null,
            used_transaction_id: $voucher->used_transaction_id,
            notes: $voucher->notes,
            created_by: $voucher->created_by,
            customer: $voucher->relationLoaded('customer') && $voucher->customer ? [
                'id' => $voucher->customer->id,
                'name' => $voucher->customer->name,
                'no_telp' => $voucher->customer->no_telp,
            ] : null,
            creator: $voucher->relationLoaded('creator') && $voucher->creator ? [
                'id' => $voucher->creator->id,
                'name' => $voucher->creator->name,
            ] : null,
            status_label: $voucher->currentStatusLabel(),
        );
    }
}
