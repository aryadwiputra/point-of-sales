<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOutletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9_-]+$/', 'unique:outlets,code'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'is_sales_enabled' => ['boolean'],
            'warehouse_name' => ['required', 'string', 'max:100'],
            'warehouse_code' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9_-]+$/', 'unique:warehouses,code'],
        ];
    }
}
