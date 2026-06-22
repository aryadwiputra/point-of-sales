<?php

declare(strict_types=1);

namespace App\Http\Requests\StockOpname;

use Illuminate\Foundation\Http\FormRequest;

class ShowStockOpnameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_search' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function productFilters(): array
    {
        return [
            'search' => $this->input('product_search', ''),
        ];
    }
}
