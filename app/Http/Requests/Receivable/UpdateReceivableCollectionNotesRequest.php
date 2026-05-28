<?php

declare(strict_types=1);

namespace App\Http\Requests\Receivable;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReceivableCollectionNotesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'collection_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
