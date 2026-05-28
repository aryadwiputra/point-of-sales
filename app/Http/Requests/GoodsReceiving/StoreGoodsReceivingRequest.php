<?php

declare(strict_types=1);

namespace App\Http\Requests\GoodsReceiving;

use Illuminate\Foundation\Http\FormRequest;

class StoreGoodsReceivingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'purchase_order_id' => ['required', 'exists:purchase_orders,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['required', 'exists:purchase_order_items,id'],
            'items.*.qty_received' => ['required', 'integer', 'min:1'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
