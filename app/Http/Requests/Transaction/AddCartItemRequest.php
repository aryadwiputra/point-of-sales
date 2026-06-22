<?php

declare(strict_types=1);

namespace App\Http\Requests\Transaction;

use Illuminate\Foundation\Http\FormRequest;

class AddCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'exists:products,id'],
            'product_unit_id' => ['nullable', 'exists:product_units,id'],
            'qty' => ['required', 'numeric', 'min:0.001'],
            ...$this->cartContextRules(),
        ];
    }

    public function cartContext(): array
    {
        return $this->safe()->only(array_keys($this->cartContextRules()));
    }

    private function cartContextRules(): array
    {
        return [
            'customer_id' => ['nullable', 'exists:customers,id'],
            'discount' => ['nullable', 'integer', 'min:0'],
            'shipping_cost' => ['nullable', 'integer', 'min:0'],
            'redeem_points' => ['nullable', 'integer', 'min:0'],
            'customer_voucher_id' => ['nullable', 'integer', 'exists:customer_vouchers,id'],
        ];
    }
}
