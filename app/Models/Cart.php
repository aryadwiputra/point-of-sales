<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = [
        'cashier_id', 'warehouse_id', 'product_id', 'unit_id', 'conversion_factor', 'qty', 'price', 'hold_id', 'hold_label', 'held_at',
    ];

    protected $casts = [
        'held_at' => 'datetime',
        'conversion_factor' => 'decimal:4',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function scopeActive($query)
    {
        return $query->whereNull('hold_id');
    }

    public function scopeHeld($query)
    {
        return $query->whereNotNull('hold_id');
    }

    public function scopeForHold($query, $holdId)
    {
        return $query->where('hold_id', $holdId);
    }
}
