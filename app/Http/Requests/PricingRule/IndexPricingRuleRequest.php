<?php

declare(strict_types=1);

namespace App\Http\Requests\PricingRule;

use Illuminate\Foundation\Http\FormRequest;

class IndexPricingRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:30'],
            'target_type' => ['nullable', 'string', 'max:30'],
            'kind' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function filters(): array
    {
        return [
            'search' => $this->input('search'),
            'status' => $this->input('status'),
            'target_type' => $this->input('target_type'),
            'kind' => $this->input('kind'),
        ];
    }
}
