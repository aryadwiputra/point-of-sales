<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashierShift extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_FORCE_CLOSED = 'force_closed';

    protected $fillable = [
        'user_id',
        'warehouse_id',
        'opened_by',
        'closed_by',
        'opened_at',
        'closed_at',
        'opening_cash',
        'expected_cash',
        'actual_cash',
        'cash_sales_total',
        'non_cash_sales_total',
        'cash_refund_total',
        'non_cash_refund_total',
        'transactions_count',
        'sales_returns_count',
        'cash_difference',
        'notes',
        'close_notes',
        'status',
    ];

    protected $casts = [
        'id' => 'integer',
        'user_id' => 'integer',
        'opened_by' => 'integer',
        'closed_by' => 'integer',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'opening_cash' => 'integer',
        'expected_cash' => 'integer',
        'actual_cash' => 'integer',
        'cash_sales_total' => 'integer',
        'non_cash_sales_total' => 'integer',
        'cash_refund_total' => 'integer',
        'non_cash_refund_total' => 'integer',
        'transactions_count' => 'integer',
        'sales_returns_count' => 'integer',
        'cash_difference' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function openedBy()
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function salesReturns()
    {
        return $this->hasMany(SalesReturn::class);
    }

    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }
}
