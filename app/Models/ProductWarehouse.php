<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ProductWarehouse extends Pivot
{
    public $incrementing = true;

    protected $table = 'product_warehouse';

    protected function casts(): array
    {
        return [
            'stock' => 'integer',
        ];
    }
}
