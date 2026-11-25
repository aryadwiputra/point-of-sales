<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KitchenStationCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'kitchen_station_id',
        'category_id',
        'outlet_id',
    ];

    protected $casts = [
        'kitchen_station_id' => 'integer',
        'category_id' => 'integer',
        'outlet_id' => 'integer',
    ];

    public function station(): BelongsTo
    {
        return $this->belongsTo(KitchenStation::class, 'kitchen_station_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
