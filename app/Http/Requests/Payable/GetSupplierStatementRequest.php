<?php

declare(strict_types=1);

namespace App\Http\Requests\Payable;

use Illuminate\Foundation\Http\FormRequest;

class GetSupplierStatementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'exists:suppliers,id'],
        ];
    }
}
