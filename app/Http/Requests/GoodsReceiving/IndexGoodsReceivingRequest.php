<?php

declare(strict_types=1);

namespace App\Http\Requests\GoodsReceiving;

use Illuminate\Foundation\Http\FormRequest;

class IndexGoodsReceivingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'purchase_order_id' => ['nullable', 'integer', 'exists:purchase_orders,id'],
        ];
    }

    public function filters(): array
    {
        return [
            'search' => $this->input('search'),
            'purchase_order_id' => $this->input('purchase_order_id'),
        ];
    }
}
