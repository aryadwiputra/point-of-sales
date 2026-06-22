<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ProductUnit extends Pivot
{
    public $incrementing = true;

    protected $table = 'product_units';

    protected function casts(): array
    {
        return [
            'is_base' => 'boolean',
            'conversion_factor' => 'decimal:4',
            'buy_price' => 'integer',
            'sell_price' => 'integer',
        ];
    }
}
