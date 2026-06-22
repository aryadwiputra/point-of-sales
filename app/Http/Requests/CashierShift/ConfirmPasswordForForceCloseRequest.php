<?php

declare(strict_types=1);

namespace App\Http\Requests\CashierShift;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmPasswordForForceCloseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function recentlyConfirmed(): bool
    {
        $confirmedAt = (int) $this->session()->get('auth.password_confirmed_at', 0);

        return $confirmedAt > 0 && (time() - $confirmedAt) <= (int) config('auth.password_timeout', 900);
    }
}
