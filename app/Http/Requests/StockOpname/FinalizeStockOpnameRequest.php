<?php

declare(strict_types=1);

namespace App\Http\Requests\StockOpname;

use Illuminate\Foundation\Http\FormRequest;

class FinalizeStockOpnameRequest extends FormRequest
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
