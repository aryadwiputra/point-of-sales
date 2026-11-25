<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KitchenTicket extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';

    public const STATUS_READY = 'ready';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'kitchen_station_id',
        'outlet_id',
        'dine_order_id',
        'transaction_id',
        'status',
        'order_type',
        'placed_at',
        'ready_at',
        'closed_at',
    ];

    protected $attributes = [
        'status' => self::STATUS_OPEN,
    ];

    protected $casts = [
        'kitchen_station_id' => 'integer',
        'outlet_id' => 'integer',
        'dine_order_id' => 'integer',
        'transaction_id' => 'integer',
        'placed_at' => 'datetime',
        'ready_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function station(): BelongsTo
    {
        return $this->belongsTo(KitchenStation::class, 'kitchen_station_id');
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function dineOrder(): BelongsTo
    {
        return $this->belongsTo(DineOrder::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(KitchenTicketItem::class);
    }
}
