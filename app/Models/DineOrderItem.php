<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DineOrderItem extends Model
{
    use HasFactory;

    protected $attributes = [
        'unit_cost' => 0,
        'tax_rate' => 0,
    ];

    protected $fillable = [
        'dine_order_id',
        'dine_bill_id',
        'product_id',
        'unit_id',
        'conversion_factor',
        'qty',
        'price',
        'unit_cost',
        'tax_type',
        'tax_rate',
        'note',
        'voided_at',
        'voided_by',
        'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'price' => 'integer',
            'unit_cost' => 'integer',
            'tax_rate' => 'decimal:2',
            'conversion_factor' => 'decimal:4',
            'voided_at' => 'datetime',
        ];
    }

    /**
     * Base-unit quantity consumed by this line: selling qty x conversion factor.
     */
    public function baseQuantity(): int
    {
        return (int) round($this->qty * (float) ($this->conversion_factor ?? 1));
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

    public function modifiers()
    {
        return $this->hasMany(DineOrderItemModifier::class);
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(DineBill::class, 'dine_bill_id');
    }

    public function batchAllocations()
    {
        return $this->hasMany(DineOrderItemBatchAllocation::class);
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function scopeActive($query)
    {
        return $query->whereNull('voided_at');
    }
}
