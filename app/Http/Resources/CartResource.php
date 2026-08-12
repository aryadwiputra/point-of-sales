<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'title' => $this->product->title,
                'barcode' => $this->product->barcode,
                'sku' => $this->product->sku,
                'sell_price' => (float) $this->product->sell_price,
                'image' => $this->product->image
                    ? (str_starts_with($this->product->image, 'http') ? $this->product->image : url('storage/'.ltrim($this->product->image, '/')))
                    : null,
            ]),
            'unit' => $this->whenLoaded('unit', fn () => [
                'id' => $this->unit->id,
                'name' => $this->unit->name,
                'symbol' => $this->unit->symbol ?? $this->unit->name,
            ]),
            'qty' => (float) $this->qty,
            'conversion_factor' => (float) $this->conversion_factor,
            'price' => (float) $this->price,
            'hold_id' => $this->hold_id,
            'hold_label' => $this->hold_label,
            'held_at' => optional($this->held_at)->toISOString(),
        ];
    }
}
