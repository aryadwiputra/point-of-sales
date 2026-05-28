<?php

declare(strict_types=1);

namespace App\Http\Requests\PurchaseOrder;

use Illuminate\Foundation\Http\FormRequest;

class IndexPurchaseOrderRequest extends FormRequest
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
            'search' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function filters(): array
    {
        return [
            'status' => $this->input('status'),
            'supplier' => $this->input('supplier'),
            'search' => $this->input('search'),
        ];
    }
}
