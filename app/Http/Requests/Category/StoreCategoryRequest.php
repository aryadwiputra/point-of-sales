<?php

declare(strict_types=1);

namespace App\Http\Requests\Category;

use App\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'image' => [
                Setting::productDisplayMode() === Setting::PRODUCT_DISPLAY_COMPACT_LIST ? 'nullable' : 'required',
                'image',
                'mimes:jpeg,jpg,png',
                'max:2048',
            ],
            'name' => ['required', 'string'],
            'description' => ['required', 'string'],
        ];
    }
}
