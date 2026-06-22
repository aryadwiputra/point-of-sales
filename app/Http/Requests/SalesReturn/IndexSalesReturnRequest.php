<?php

declare(strict_types=1);

namespace App\Http\Requests\SalesReturn;

use Illuminate\Foundation\Http\FormRequest;

class IndexSalesReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['nullable', 'string', 'max:100'],
            'invoice' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'return_type' => ['nullable', 'string', 'in:refund_cash,store_credit'],
        ];
    }

    public function filters(): array
    {
        return [
            'code' => $this->input('code'),
            'invoice' => $this->input('invoice'),
            'date_from' => $this->input('date_from'),
            'date_to' => $this->input('date_to'),
            'return_type' => $this->input('return_type'),
        ];
    }
}
