<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice' => $this->invoice,
            'cashier' => $this->whenLoaded('cashier', fn () => [
                'id' => $this->cashier->id,
                'name' => $this->cashier->name,
            ]),
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'phone' => $this->customer->phone,
            ]),
            'warehouse' => $this->whenLoaded('warehouse', fn () => [
                'id' => $this->warehouse->id,
                'name' => $this->warehouse->name,
            ]),
            'subtotal' => (float) $this->subtotal,
            'discount' => (float) $this->discount,
            'shipping_cost' => (float) $this->shipping_cost,
            'tax_rate' => (float) $this->tax_rate,
            'tax_total' => (float) $this->tax_total,
            'loyalty_discount_total' => (float) $this->loyalty_discount_total,
            'customer_voucher_discount' => (float) $this->customer_voucher_discount,
            'grand_total' => (float) $this->grand_total,
            'cash' => (float) $this->cash,
            'change' => (float) $this->change,
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'payment_reference' => $this->payment_reference,
            'payment_url' => $this->payment_url,
            'discount_approval_status' => $this->discount_approval_status,
            'created_at' => optional($this->created_at)->toISOString(),
            'details' => $this->whenLoaded('details', fn () => $this->details->map(fn ($d) => [
                'id' => $d->id,
                'product_id' => $d->product_id,
                'product_name' => $d->product?->title,
                'qty' => (float) $d->qty,
                'unit_price' => (float) $d->unit_price,
                'price' => (float) $d->price,
                'discount_total' => (float) $d->discount_total,
            ])),
        ];
    }
}
