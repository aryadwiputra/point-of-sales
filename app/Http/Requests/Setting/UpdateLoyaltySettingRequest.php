<?php

declare(strict_types=1);

namespace App\Http\Requests\Setting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class UpdateLoyaltySettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'enable_earn' => ['required', 'boolean'],
            'enable_redeem' => ['required', 'boolean'],
            'earn_rate_amount' => ['required', 'integer', 'min:1'],
            'redeem_point_value' => ['required', 'integer', 'min:1'],
            'tiers' => ['required', 'array'],
            'tiers.regular' => ['required', 'integer', 'min:0'],
            'tiers.silver' => ['required', 'integer', 'min:0'],
            'tiers.gold' => ['required', 'integer', 'min:0'],
            'tiers.platinum' => ['required', 'integer', 'min:0'],
        ];
    }

    public function payload(): array
    {
        $validated = $this->validated();

        $orderedThresholds = [
            'regular' => (int) $validated['tiers']['regular'],
            'silver' => (int) $validated['tiers']['silver'],
            'gold' => (int) $validated['tiers']['gold'],
            'platinum' => (int) $validated['tiers']['platinum'],
        ];

        if (
            $orderedThresholds['silver'] < $orderedThresholds['regular']
            || $orderedThresholds['gold'] < $orderedThresholds['silver']
            || $orderedThresholds['platinum'] < $orderedThresholds['gold']
        ) {
            throw ValidationException::withMessages([
                'tiers' => 'Threshold tier harus berurutan dari Regular ke Platinum.',
            ]);
        }

        return [
            ...$validated,
            'tiers' => $orderedThresholds,
        ];
    }
}
