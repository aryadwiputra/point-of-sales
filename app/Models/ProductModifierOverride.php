<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductModifierOverride extends Model
{
    protected $fillable = ['product_id', 'modifier_id', 'price_delta', 'is_active'];

    protected $casts = [
        'product_id' => 'integer',
        'modifier_id' => 'integer',
        'price_delta' => 'integer',
        'is_active' => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function modifier()
    {
        return $this->belongsTo(Modifier::class);
    }
}
