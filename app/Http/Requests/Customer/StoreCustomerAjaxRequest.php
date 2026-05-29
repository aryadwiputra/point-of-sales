<?php

declare(strict_types=1);

namespace App\Http\Requests\Customer;

use App\Services\LoyaltyService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerAjaxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'no_telp' => ['required', 'string', Rule::unique('customers', 'no_telp')],
            'address' => ['required', 'string'],
            'is_loyalty_member' => ['nullable', 'boolean'],
            'loyalty_tier' => ['nullable', 'string', Rule::in(array_keys(app(LoyaltyService::class)->tiers()))],
            'province_id' => ['nullable', 'string'],
            'regency_id' => ['nullable', 'string'],
            'district_id' => ['nullable', 'string'],
            'village_id' => ['nullable', 'string'],
        ];
    }
}
