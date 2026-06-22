<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesReturn extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'transaction_id',
        'warehouse_id',
        'customer_id',
        'cashier_id',
        'cashier_shift_id',
        'status',
        'return_type',
        'refund_amount',
        'credited_amount',
        'total_return_amount',
        'notes',
        'completed_at',
    ];

    protected $casts = [
        'transaction_id' => 'integer',
        'customer_id' => 'integer',
        'cashier_id' => 'integer',
        'cashier_shift_id' => 'integer',
        'refund_amount' => 'integer',
        'credited_amount' => 'integer',
        'total_return_amount' => 'integer',
        'completed_at' => 'datetime',
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function cashierShift()
    {
        return $this->belongsTo(CashierShift::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items()
    {
        return $this->hasMany(SalesReturnItem::class);
    }

    public function customerCredits()
    {
        return $this->hasMany(CustomerCredit::class);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }
}
