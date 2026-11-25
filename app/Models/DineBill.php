<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DineBill extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';

    public const STATUS_PENDING_APPROVAL = 'pending_approval';

    public const STATUS_SETTLED = 'settled';

    public const STATUS_VOID = 'void';

    protected $fillable = [
        'dine_session_id',
        'sequence',
        'status',
        'customer_id',
        'subtotal',
        'discount_total',
        'tax_total',
        'grand_total',
        'transaction_id',
        'settled_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'subtotal' => 'integer',
            'discount_total' => 'integer',
            'tax_total' => 'integer',
            'grand_total' => 'integer',
            'settled_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(DineSession::class, 'dine_session_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DineOrderItem::class, 'dine_bill_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_OPEN);
    }
}
