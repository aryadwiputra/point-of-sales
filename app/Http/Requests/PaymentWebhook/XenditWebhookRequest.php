<?php

declare(strict_types=1);

namespace App\Http\Requests\PaymentWebhook;

class XenditWebhookRequest extends PaymentWebhookRequest
{
    public function rules(): array
    {
        return [
            'external_id' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', 'max:100'],
            'id' => ['required', 'string', 'max:255'],
        ];
    }
}
