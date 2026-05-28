<?php

declare(strict_types=1);

namespace App\Http\Requests\Receivable;

use Illuminate\Foundation\Http\FormRequest;

class GetCustomerStatementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'exists:customers,id'],
        ];
    }
}
