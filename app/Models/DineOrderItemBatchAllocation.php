<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DineOrderItemBatchAllocation extends Model
{
    protected $fillable = [
        'dine_order_item_id',
        'product_batch_id',
        'qty',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(DineOrderItem::class, 'dine_order_item_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'product_batch_id');
    }
}
