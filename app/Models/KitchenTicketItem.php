<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KitchenTicketItem extends Model
{
    use HasFactory;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_COOKING = 'cooking';

    public const STATUS_READY = 'ready';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'kitchen_ticket_id',
        'dine_order_item_id',
        'transaction_detail_id',
        'product_name',
        'qty',
        'modifiers_text',
        'note',
        'status',
        'cancel_reason',
        'started_at',
        'ready_at',
        'cancelled_at',
    ];

    protected $attributes = [
        'status' => self::STATUS_QUEUED,
    ];

    protected $casts = [
        'kitchen_ticket_id' => 'integer',
        'dine_order_item_id' => 'integer',
        'transaction_detail_id' => 'integer',
        'qty' => 'integer',
        'started_at' => 'datetime',
        'ready_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(KitchenTicket::class, 'kitchen_ticket_id');
    }

    public function dineOrderItem(): BelongsTo
    {
        return $this->belongsTo(DineOrderItem::class);
    }

    public function transactionDetail(): BelongsTo
    {
        return $this->belongsTo(TransactionDetail::class);
    }

    public function isActive(): bool
    {
        return $this->status !== self::STATUS_CANCELLED;
    }
}
