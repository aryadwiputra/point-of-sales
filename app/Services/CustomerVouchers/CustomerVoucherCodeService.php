<?php

declare(strict_types=1);

namespace App\Services\CustomerVouchers;

use App\Models\CustomerVoucher;
use Illuminate\Support\Str;

class CustomerVoucherCodeService
{
    public function generate(): string
    {
        do {
            $code = 'VCR-'.Str::upper(Str::random(8));
        } while (CustomerVoucher::query()->where('code', $code)->exists());

        return $code;
    }
}
