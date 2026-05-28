<?php

declare(strict_types=1);

namespace App\Http\Requests\CrmReminder;

use Illuminate\Foundation\Http\FormRequest;

class IndexCrmReminderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function filters(): array
    {
        return [
            'type' => $this->input('type'),
            'status' => $this->input('status'),
        ];
    }
}
