<?php

declare(strict_types=1);

namespace App\Http\Requests\StockMutation;

use Illuminate\Foundation\Http\FormRequest;

class IndexStockMutationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'mutation_type' => ['nullable', 'string', 'max:50'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ];
    }

    public function filters(): array
    {
        return [
            'product_id' => $this->input('product_id'),
            'mutation_type' => $this->input('mutation_type'),
            'date_from' => $this->input('date_from'),
            'date_to' => $this->input('date_to'),
        ];
    }
}
