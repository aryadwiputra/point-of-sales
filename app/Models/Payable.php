<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payable extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'supplier_id',
        'document_number',
        'total',
        'paid',
        'due_date',
        'status',
        'note',
        'purchase_order_id',
    ];

    protected $casts = [
        'total' => 'float',
        'paid' => 'float',
        'due_date' => 'date',
    ];

    protected $appends = [
        'remaining',
        'aging_bucket',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function payments()
    {
        return $this->hasMany(PayablePayment::class);
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function getRemainingAttribute(): float
    {
        return max(0, ($this->total ?? 0) - ($this->paid ?? 0));
    }

    public function getAgingBucketAttribute(): string
    {
        if ($this->status === 'paid') {
            return 'paid';
        }

        if (! $this->due_date) {
            return 'no_due_date';
        }

        if (! now()->gt($this->due_date)) {
            return 'current';
        }

        $daysOverdue = now()->diffInDays($this->due_date);

        return match (true) {
            $daysOverdue <= 30 => '0-30',
            $daysOverdue <= 60 => '31-60',
            $daysOverdue <= 90 => '61-90',
            default => '90+',
        };
    }
}
