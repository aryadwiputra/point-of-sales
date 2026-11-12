<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'supplier_id',
        'warehouse_id',
        'document_number',
        'status',
        'notes',
        'created_by',
        'ordered_at',
        'completed_at',
    ];

    protected $casts = [
        'ordered_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function goodsReceivings()
    {
        return $this->hasMany(GoodsReceiving::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function payable()
    {
        return $this->hasOne(Payable::class);
    }
}
