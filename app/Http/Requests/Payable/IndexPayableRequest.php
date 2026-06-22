<?php

declare(strict_types=1);

namespace App\Http\Requests\Payable;

use Illuminate\Foundation\Http\FormRequest;

class IndexPayableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', 'max:30'],
            'supplier' => ['nullable', 'integer', 'exists:suppliers,id'],
            'invoice' => ['nullable', 'string', 'max:100'],
            'due_from' => ['nullable', 'date'],
            'due_to' => ['nullable', 'date'],
        ];
    }

    public function filters(): array
    {
        return [
            'status' => $this->input('status'),
            'supplier' => $this->input('supplier'),
            'invoice' => $this->input('invoice'),
            'due_from' => $this->input('due_from'),
            'due_to' => $this->input('due_to'),
        ];
    }
}
