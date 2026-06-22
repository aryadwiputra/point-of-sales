<?php

declare(strict_types=1);

namespace App\Http\Requests\Member;

use App\Services\LoyaltyService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'tier' => ['nullable', 'string', Rule::in(array_keys(app(LoyaltyService::class)->tiers()))],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive', 'all'])],
        ];
    }

    public function filters(): array
    {
        return [
            'search' => trim((string) $this->string('search')->value()),
            'tier' => trim((string) $this->string('tier')->value()),
            'status' => $this->string('status')->value() ?: 'active',
        ];
    }
}
