<?php

declare(strict_types=1);

namespace App\Services\StockOpnames;

use App\Models\StockOpname;
use Illuminate\Support\Str;

class CreateStockOpnameService
{
    public function execute(array $data, ?int $userId): StockOpname
    {
        return StockOpname::create([
            'code' => $this->generateCode(),
            'notes' => $data['notes'] ?? null,
            'status' => 'draft',
            'created_by' => $userId,
        ]);
    }

    private function generateCode(): string
    {
        do {
            $code = 'SO-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));
        } while (StockOpname::where('code', $code)->exists());

        return $code;
    }
}
