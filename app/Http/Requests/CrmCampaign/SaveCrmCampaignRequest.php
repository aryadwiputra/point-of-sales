<?php

declare(strict_types=1);

namespace App\Http\Requests\CrmCampaign;

use App\Models\CustomerCampaign;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class SaveCrmCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in([
                CustomerCampaign::TYPE_PROMO_BROADCAST,
                CustomerCampaign::TYPE_INVOICE_SHARE,
                CustomerCampaign::TYPE_DUE_DATE_REMINDER,
                CustomerCampaign::TYPE_REPEAT_ORDER_REMINDER,
            ])],
            'channel' => ['required', Rule::in([
                CustomerCampaign::CHANNEL_INTERNAL,
                CustomerCampaign::CHANNEL_WHATSAPP_LINK,
            ])],
            'message_template' => ['nullable', 'string', 'max:4000'],
            'audience_filters' => ['nullable', 'array'],
            'audience_filters.segment_ids' => ['nullable', 'array'],
            'audience_filters.segment_ids.*' => ['integer', 'exists:customer_segments,id'],
            'audience_filters.customer_type' => ['nullable', Rule::in(['all', 'member', 'non_member'])],
            'audience_filters.receivable_status' => ['nullable', Rule::in(['all', 'has_receivable', 'overdue', 'due_soon'])],
            'audience_filters.voucher_filter' => ['nullable', Rule::in(['all', 'has_active_voucher', 'no_active_voucher'])],
            'save_as_draft' => ['nullable', 'boolean'],
        ];
    }

    public function campaignData(): array
    {
        return Arr::except($this->validated(), ['save_as_draft']);
    }

    public function saveAsDraft(): bool
    {
        return $this->boolean('save_as_draft');
    }
}
