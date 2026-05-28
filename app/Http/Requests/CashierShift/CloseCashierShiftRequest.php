<?php

declare(strict_types=1);

namespace App\Http\Requests\CashierShift;

use Illuminate\Foundation\Http\FormRequest;

class CloseCashierShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'actual_cash' => ['required', 'integer', 'min:0'],
            'close_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
