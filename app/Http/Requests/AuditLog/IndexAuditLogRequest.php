<?php

declare(strict_types=1);

namespace App\Http\Requests\AuditLog;

use Illuminate\Foundation\Http\FormRequest;

class IndexAuditLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'module' => ['nullable', 'string', 'max:100'],
            'event' => ['nullable', 'string', 'max:150'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function filters(): array
    {
        return [
            'user_id' => $this->input('user_id'),
            'module' => $this->input('module'),
            'event' => $this->input('event'),
            'date_from' => $this->input('date_from'),
            'date_to' => $this->input('date_to'),
            'search' => $this->input('search'),
        ];
    }
}
