<?php

declare(strict_types=1);

namespace App\Http\Requests\StockOpname;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStockOpnameItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'physical_stock' => ['nullable', 'integer', 'min:0'],
            'adjustment_reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
