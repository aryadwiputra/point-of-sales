<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionDetailBatchAllocation extends Model
{
    protected $fillable = [
        'transaction_detail_id',
        'product_batch_id',
        'qty',
    ];

    protected $casts = [
        'qty' => 'integer',
    ];

    public function transactionDetail()
    {
        return $this->belongsTo(TransactionDetail::class);
    }

    public function productBatch()
    {
        return $this->belongsTo(ProductBatch::class);
    }
}
