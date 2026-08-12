<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashierShiftResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'warehouse' => $this->whenLoaded('warehouse', fn () => [
                'id' => $this->warehouse->id,
                'code' => $this->warehouse->code,
                'name' => $this->warehouse->name,
            ]),
            'opening_cash' => (float) $this->opening_cash,
            'closing_cash' => $this->closing_cash !== null ? (float) $this->closing_cash : null,
            'expected_cash' => $this->expected_cash !== null ? (float) $this->expected_cash : null,
            'cash_difference' => $this->cash_difference !== null ? (float) $this->cash_difference : null,
            'status' => $this->status,
            'opened_at' => optional($this->opened_at)->toISOString(),
            'closed_at' => optional($this->closed_at)->toISOString(),
            'notes' => $this->notes,
            'sales_count' => $this->whenCounted('transactions'),
            'sales_total' => $this->whenLoaded('transactions', fn () => (float) $this->transactions->sum('grand_total')),
        ];
    }
}
