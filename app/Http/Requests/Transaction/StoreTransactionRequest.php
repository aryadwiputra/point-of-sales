<?php

declare(strict_types=1);

namespace App\Http\Requests\Transaction;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'discount' => ['nullable', 'integer', 'min:0'],
            'shipping_cost' => ['nullable', 'integer', 'min:0'],
            'redeem_points' => ['nullable', 'integer', 'min:0'],
            'customer_voucher_id' => ['nullable', 'integer', 'exists:customer_vouchers,id'],
            'cash' => ['nullable', 'integer', 'min:0'],
            'payment_gateway' => ['nullable', 'string'],
            'bank_account_id' => ['nullable', 'integer', 'exists:bank_accounts,id'],
            'pay_later' => ['nullable', 'boolean'],
            'due_date' => ['nullable', 'date'],
        ];
    }
}
