<?php

declare(strict_types=1);

namespace App\Http\Requests\CustomerSegment;

use App\Models\CustomerSegment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexCustomerSegmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', Rule::in([
                CustomerSegment::TYPE_MANUAL,
                CustomerSegment::TYPE_AUTO,
            ])],
        ];
    }

    public function filters(): array
    {
        return [
            'search' => $this->input('search'),
            'type' => $this->input('type'),
        ];
    }
}
