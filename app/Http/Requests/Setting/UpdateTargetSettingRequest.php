<?php

declare(strict_types=1);

namespace App\Http\Requests\Setting;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTargetSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'monthly_sales_target' => ['required', 'numeric', 'min:0'],
        ];
    }
}
