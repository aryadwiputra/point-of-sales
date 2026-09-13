<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionTenderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'method' => $this->method,
            'amount' => (float) $this->amount,
            'cash_received' => $this->cash_received !== null ? (float) $this->cash_received : null,
            'change' => (float) $this->change,
            'bank_account_id' => $this->bank_account_id,
            'payment_status' => $this->payment_status,
            'payment_reference' => $this->payment_reference,
            'payment_url' => $this->payment_url,
            'qr_string' => $this->qr_string,
            'paid_at' => optional($this->paid_at)->toISOString(),
        ];
    }
}
