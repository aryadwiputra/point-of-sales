<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesReturnItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_return_id',
        'transaction_detail_id',
        'product_id',
        'qty_sold',
        'qty_returned_before',
        'qty_return',
        'unit_price',
        'subtotal',
        'return_reason',
        'restock_to_inventory',
    ];

    protected $casts = [
        'sales_return_id' => 'integer',
        'transaction_detail_id' => 'integer',
        'product_id' => 'integer',
        'qty_sold' => 'decimal:3',
        'qty_returned_before' => 'decimal:3',
        'qty_return' => 'decimal:3',
        'unit_price' => 'integer',
        'subtotal' => 'integer',
        'restock_to_inventory' => 'boolean',
    ];

    public function salesReturn()
    {
        return $this->belongsTo(SalesReturn::class);
    }

    public function transactionDetail()
    {
        return $this->belongsTo(TransactionDetail::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
