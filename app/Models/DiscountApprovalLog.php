<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiscountApprovalLog extends Model
{
    protected $fillable = [
        'transaction_id', 'cashier_id', 'requested_discount', 'status', 'responded_by', 'responded_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'requested_discount' => 'integer',
            'responded_at' => 'datetime',
        ];
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function responder()
    {
        return $this->belongsTo(User::class, 'responded_by');
    }
}
