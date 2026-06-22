<?php

declare(strict_types=1);

namespace App\Http\Requests\CashierShift;

use Illuminate\Foundation\Http\FormRequest;

class IndexCashierShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cashier_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', 'string', 'max:30'],
            'opened_from' => ['nullable', 'date'],
            'opened_to' => ['nullable', 'date'],
        ];
    }

    public function filters(): array
    {
        return [
            'cashier_id' => $this->input('cashier_id'),
            'status' => $this->input('status'),
            'opened_from' => $this->input('opened_from'),
            'opened_to' => $this->input('opened_to'),
        ];
    }
}
