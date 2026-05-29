<?php

declare(strict_types=1);

namespace App\Http\Requests\CustomerVoucher;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexCustomerVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(['active', 'scheduled', 'expired', 'used', 'inactive'])],
        ];
    }

    public function filters(): array
    {
        return [
            'search' => $this->input('search'),
            'status' => $this->input('status'),
        ];
    }
}
