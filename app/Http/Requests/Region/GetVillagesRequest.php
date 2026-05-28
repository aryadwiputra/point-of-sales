<?php

declare(strict_types=1);

namespace App\Http\Requests\Region;

use Illuminate\Foundation\Http\FormRequest;

class GetVillagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'district_id' => ['required', 'string'],
        ];
    }
}
