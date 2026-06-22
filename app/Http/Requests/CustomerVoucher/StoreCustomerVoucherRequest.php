<?php

declare(strict_types=1);

namespace App\Http\Requests\CustomerVoucher;

use App\Models\CustomerVoucher;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('customer_vouchers', 'code')],
            'name' => ['required', 'string', 'max:255'],
            'discount_type' => ['required', Rule::in([
                CustomerVoucher::TYPE_FIXED_AMOUNT,
                CustomerVoucher::TYPE_PERCENTAGE,
            ])],
            'discount_value' => ['required', 'numeric', 'min:0.01'],
            'minimum_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(fn ($validator) => $this->validatePercentageDiscount($validator));
    }

    public function normalizedData(): array
    {
        $validated = $this->validated();
        $validated['is_active'] = (bool) ($validated['is_active'] ?? false);
        $validated['minimum_order'] = max(0, (int) ($validated['minimum_order'] ?? 0));

        return $validated;
    }

    private function validatePercentageDiscount($validator): void
    {
        if (
            $this->input('discount_type') === CustomerVoucher::TYPE_PERCENTAGE
            && (float) $this->input('discount_value') > 100
        ) {
            $validator->errors()->add('discount_value', 'The discount value field must not be greater than 100.');
        }
    }
}
