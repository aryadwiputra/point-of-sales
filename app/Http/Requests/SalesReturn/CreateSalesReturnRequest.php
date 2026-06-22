<?php

declare(strict_types=1);

namespace App\Http\Requests\SalesReturn;

use Illuminate\Foundation\Http\FormRequest;

class CreateSalesReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }
}
