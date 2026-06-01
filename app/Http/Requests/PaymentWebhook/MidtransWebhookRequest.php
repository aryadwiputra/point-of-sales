<?php

declare(strict_types=1);

namespace App\Http\Requests\PaymentWebhook;

class MidtransWebhookRequest extends PaymentWebhookRequest
{
    public function rules(): array
    {
        return [
            'order_id' => ['required', 'string', 'max:255'],
            'status_code' => ['required', 'string', 'max:10'],
            'gross_amount' => ['required', 'string', 'max:50'],
            'signature_key' => ['required', 'string', 'max:255'],
            'transaction_status' => ['required', 'string', 'max:100'],
            'fraud_status' => ['nullable', 'string', 'max:100'],
            'transaction_id' => ['nullable', 'string', 'max:255'],
        ];
    }
}
