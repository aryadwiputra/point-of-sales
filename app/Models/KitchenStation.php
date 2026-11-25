<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KitchenStation extends Model
{
    use HasFactory;

    protected $fillable = [
        'outlet_id',
        'name',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'outlet_id' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'kitchen_station_categories')
            ->withPivot('outlet_id')
            ->withTimestamps();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'kitchen_station_user')->withTimestamps();
    }

    public function productOverrides(): HasMany
    {
        return $this->hasMany(ProductKitchenStationOverride::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(KitchenTicket::class);
    }
}
