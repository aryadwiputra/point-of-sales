<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionDetail extends Model
{
    use HasFactory;

    /**
     * fillable
     *
     * @var array
     */
    protected $fillable = [
        'transaction_id',
        'product_id',
        'product_unit_id',
        'unit_label',
        'unit_conversion_qty',
        'qty',
        'base_unit_price',
        'unit_price',
        'unit_buy_price',
        'price',
        'discount_total',
        'pricing_rule_id',
        'pricing_rule_name',
        'pricing_rule_kind',
        'pricing_group_key',
        'pricing_group_label',
    ];

    protected $casts = [
        'qty' => 'decimal:3',
        'unit_conversion_qty' => 'decimal:3',
        'base_unit_price' => 'integer',
        'unit_price' => 'integer',
        'unit_buy_price' => 'integer',
        'price' => 'integer',
        'discount_total' => 'integer',
        'pricing_rule_id' => 'integer',
        'pricing_rule_kind' => 'string',
    ];

    /**
     * transaction
     *
     * @return void
     */
    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * product
     *
     * @return void
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function productUnit()
    {
        return $this->belongsTo(ProductUnit::class);
    }

    public function pricingRule()
    {
        return $this->belongsTo(PricingRule::class);
    }

    public function salesReturnItems()
    {
        return $this->hasMany(SalesReturnItem::class);
    }
}
