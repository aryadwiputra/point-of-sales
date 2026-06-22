<?php

declare(strict_types=1);

namespace App\Http\Requests\Receivable;

use Illuminate\Foundation\Http\FormRequest;

class IndexReceivableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', 'max:30'],
            'customer' => ['nullable', 'integer', 'exists:customers,id'],
            'invoice' => ['nullable', 'string', 'max:100'],
            'due_from' => ['nullable', 'date'],
            'due_to' => ['nullable', 'date'],
        ];
    }

    public function filters(): array
    {
        return [
            'status' => $this->input('status'),
            'customer' => $this->input('customer'),
            'invoice' => $this->input('invoice'),
            'due_from' => $this->input('due_from'),
            'due_to' => $this->input('due_to'),
        ];
    }
}
