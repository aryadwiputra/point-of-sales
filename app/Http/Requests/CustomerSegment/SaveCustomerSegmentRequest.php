<?php

declare(strict_types=1);

namespace App\Http\Requests\CustomerSegment;

use App\Models\CustomerSegment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCustomerSegmentRequest extends FormRequest
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
                CustomerSegment::TYPE_MANUAL,
                CustomerSegment::TYPE_AUTO,
            ])],
            'is_active' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
            'auto_rule_type' => ['nullable', Rule::in([
                CustomerSegment::RULE_SPENDING,
                CustomerSegment::RULE_PURCHASE_FREQUENCY,
                CustomerSegment::RULE_RECEIVABLE_BEHAVIOR,
            ])],
            'rule_config' => ['nullable', 'array'],
            'rule_config.min_total_spent' => ['nullable', 'integer', 'min:0'],
            'rule_config.min_transaction_count' => ['nullable', 'integer', 'min:0'],
            'rule_config.recent_days' => ['nullable', 'integer', 'min:1'],
            'rule_config.inactivity_days_min' => ['nullable', 'integer', 'min:1'],
            'rule_config.require_outstanding_receivable' => ['nullable', 'boolean'],
            'rule_config.overdue_only' => ['nullable', 'boolean'],
        ];
    }
}
