<?php

declare(strict_types=1);

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class SyncCustomerSegmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'segment_ids' => ['nullable', 'array'],
            'segment_ids.*' => ['integer', 'exists:customer_segments,id'],
        ];
    }
}
