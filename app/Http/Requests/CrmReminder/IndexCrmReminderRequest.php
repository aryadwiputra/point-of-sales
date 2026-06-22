<?php

declare(strict_types=1);

namespace App\Http\Requests\CrmReminder;

use App\Models\CustomerCampaign;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexCrmReminderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['nullable', Rule::in([
                CustomerCampaign::TYPE_PROMO_BROADCAST,
                CustomerCampaign::TYPE_INVOICE_SHARE,
                CustomerCampaign::TYPE_DUE_DATE_REMINDER,
                CustomerCampaign::TYPE_REPEAT_ORDER_REMINDER,
            ])],
            'status' => ['nullable', Rule::in([
                CustomerCampaign::STATUS_DRAFT,
                CustomerCampaign::STATUS_READY,
                CustomerCampaign::STATUS_PROCESSED,
                CustomerCampaign::STATUS_CANCELLED,
            ])],
        ];
    }

    public function filters(): array
    {
        return [
            'type' => $this->input('type'),
            'status' => $this->input('status'),
        ];
    }
}
