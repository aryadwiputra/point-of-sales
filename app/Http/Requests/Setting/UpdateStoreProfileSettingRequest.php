<?php

declare(strict_types=1);

namespace App\Http\Requests\Setting;

use App\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStoreProfileSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'store_name' => ['required', 'string', 'max:255'],
            'store_address' => ['required', 'string', 'max:500'],
            'store_phone' => ['nullable', 'string', 'max:50'],
            'store_email' => ['nullable', 'email', 'max:255'],
            'store_website' => ['nullable', 'string', 'max:255'],
            'store_city' => ['nullable', 'string', 'max:255'],
            'store_logo' => ['nullable', 'image', 'max:2048'],
            'product_display_mode' => ['required', Rule::in(Setting::PRODUCT_DISPLAY_MODES)],
        ];
    }
}
