<?php

declare(strict_types=1);

namespace App\Http\Requests\CustomerSegment;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerSegmentMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
        ];
    }
}
