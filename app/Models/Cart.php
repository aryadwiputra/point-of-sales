<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    use HasFactory;

    /**
     * fillable
     *
     * @var array
     */
    protected $fillable = [
        'cashier_id', 'warehouse_id', 'product_id', 'qty', 'price', 'hold_id', 'hold_label', 'held_at',
    ];

    /**
     * casts
     *
     * @var array
     */
    protected $casts = [
        'held_at' => 'datetime',
    ];

    /**
     * product
     *
     * @return void
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Scope for active (not held) carts
     */
    public function scopeActive($query)
    {
        return $query->whereNull('hold_id');
    }

    /**
     * Scope for held carts
     */
    public function scopeHeld($query)
    {
        return $query->whereNotNull('hold_id');
    }

    /**
     * Scope for specific hold group
     */
    public function scopeForHold($query, $holdId)
    {
        return $query->where('hold_id', $holdId);
    }
}
