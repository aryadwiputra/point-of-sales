<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartModifier extends Model
{
    protected $fillable = [
        'cart_id', 'modifier_id', 'modifier_group_id', 'group_name', 'name', 'price_delta',
    ];

    protected $casts = [
        'cart_id' => 'integer',
        'modifier_id' => 'integer',
        'modifier_group_id' => 'integer',
        'price_delta' => 'integer',
    ];

    public function modifier()
    {
        return $this->belongsTo(Modifier::class);
    }
}
