<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DineOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'dine_order_id',
        'product_id',
        'unit_id',
        'conversion_factor',
        'qty',
        'price',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'price' => 'integer',
            'conversion_factor' => 'decimal:4',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(DineOrder::class, 'dine_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
