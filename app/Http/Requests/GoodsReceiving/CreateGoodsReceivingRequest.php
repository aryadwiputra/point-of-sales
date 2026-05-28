<?php

declare(strict_types=1);

namespace App\Http\Requests\GoodsReceiving;

use Illuminate\Foundation\Http\FormRequest;

class CreateGoodsReceivingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'purchase_order_id' => ['nullable', 'integer', 'exists:purchase_orders,id'],
        ];
    }
}
