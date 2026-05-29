<?php

declare(strict_types=1);

namespace App\Http\Requests\Member;

use App\Services\LoyaltyService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $member = $this->route('member');

        return [
            'name' => ['required', 'string', 'max:255'],
            'no_telp' => ['required', 'string', Rule::unique('customers', 'no_telp')->ignore($member?->id)],
            'address' => ['required', 'string'],
            'is_loyalty_member' => ['nullable', 'boolean'],
            'loyalty_tier' => ['nullable', 'string', Rule::in(array_keys(app(LoyaltyService::class)->tiers()))],
            'province_id' => ['required', 'string'],
            'regency_id' => ['required', 'string'],
            'district_id' => ['required', 'string'],
            'village_id' => ['required', 'string'],
        ];
    }
}
