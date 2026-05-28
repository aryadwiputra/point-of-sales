<?php

declare(strict_types=1);

namespace App\Http\Requests\Payable;

use Illuminate\Foundation\Http\FormRequest;

class StorePayableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'document_number' => ['nullable', 'string', 'max:100'],
            'total' => ['required', 'numeric', 'min:1'],
            'due_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string'],
        ];
    }
}
