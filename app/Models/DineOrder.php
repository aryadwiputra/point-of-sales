<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class DineOrder extends Model
{
    use HasFactory;

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    public const PAY_AT_COUNTER = 'pay_at_counter';

    public const PAY_ONLINE = 'pay_online';

    protected $fillable = [
        'dine_table_id',
        'customer_id',
        'access_token',
        'status',
        'notes',
        'payment_option',
        'payment_method',
        'payment_status',
        'payment_reference',
        'payment_url',
        'cashier_id',
        'transaction_id',
        'subtotal',
        'item_count',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'integer',
            'item_count' => 'integer',
            'submitted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (DineOrder $order) {
            if (empty($order->access_token)) {
                $order->access_token = (string) Str::uuid();
            }
        });
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'dine_table_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DineOrderItem::class, 'dine_order_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_SUBMITTED);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_SUBMITTED, self::STATUS_ACCEPTED]);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }
}
