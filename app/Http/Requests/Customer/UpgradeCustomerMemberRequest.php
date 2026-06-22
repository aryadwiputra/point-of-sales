<?php

declare(strict_types=1);

namespace App\Http\Requests\Customer;

use App\Services\LoyaltyService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpgradeCustomerMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'loyalty_tier' => ['nullable', 'string', Rule::in(array_keys(app(LoyaltyService::class)->tiers()))],
        ];
    }
}
