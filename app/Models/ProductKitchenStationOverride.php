<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductKitchenStationOverride extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'kitchen_station_id',
        'outlet_id',
    ];

    protected $casts = [
        'product_id' => 'integer',
        'kitchen_station_id' => 'integer',
        'outlet_id' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(KitchenStation::class, 'kitchen_station_id');
    }
}
