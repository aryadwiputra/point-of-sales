<?php

declare(strict_types=1);

namespace App\Services\Payables;

use App\Models\Payable;
use Illuminate\Support\Str;

class CreatePayableService
{
    public function execute(array $data): Payable
    {
        if (! ($data['document_number'] ?? null)) {
            $data['document_number'] = 'INV-'.Str::upper(Str::random(8));
        }

        $data['status'] = 'unpaid';
        $data['paid'] = 0;

        return Payable::create($data);
    }
}
