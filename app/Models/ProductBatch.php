<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductBatch extends Model
{
    protected $fillable = ['product_id', 'warehouse_id', 'batch_number', 'expired_at', 'received_at', 'stock'];

    protected function casts(): array
    {
        return [
            'expired_at' => 'date',
            'received_at' => 'date',
            'stock' => 'integer',
        ];
    }

    public function product() { return $this->belongsTo(Product::class); }
    public function warehouse() { return $this->belongsTo(Warehouse::class); }

    public function scopeExpiringSoon($q, int $days = 30)
    {
        return $q->where('expired_at', '<=', now()->addDays($days))
            ->where('expired_at', '>', now())
            ->where('stock', '>', 0);
    }

    public function scopeExpired($q)
    {
        return $q->where('expired_at', '<', now())->where('stock', '>', 0);
    }
}
