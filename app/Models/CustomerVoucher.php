<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerVoucher extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPE_FIXED_AMOUNT = 'fixed_amount';

    public const TYPE_PERCENTAGE = 'percentage';

    protected $fillable = [
        'customer_id',
        'code',
        'name',
        'discount_type',
        'discount_value',
        'minimum_order',
        'is_active',
        'is_used',
        'starts_at',
        'expires_at',
        'used_at',
        'used_transaction_id',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'customer_id' => 'integer',
        'discount_value' => 'float',
        'minimum_order' => 'integer',
        'is_active' => 'boolean',
        'is_used' => 'boolean',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'used_transaction_id' => 'integer',
        'created_by' => 'integer',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function usedTransaction()
    {
        return $this->belongsTo(Transaction::class, 'used_transaction_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function currentStatusLabel(): string
    {
        if ($this->is_used) {
            return 'used';
        }

        if (! $this->is_active) {
            return 'inactive';
        }

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return 'scheduled';
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return 'expired';
        }

        return 'active';
    }
}
