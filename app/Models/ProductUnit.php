<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductUnit extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'label',
        'conversion_qty',
        'is_base_unit',
        'sell_price',
        'buy_price',
        'barcode',
    ];

    protected $casts = [
        'id' => 'integer',
        'product_id' => 'integer',
        'conversion_qty' => 'decimal:3',
        'is_base_unit' => 'boolean',
        'sell_price' => 'integer',
        'buy_price' => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
