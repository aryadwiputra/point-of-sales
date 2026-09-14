<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DineArea extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'outlet_id',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'outlet_id' => 'integer',
        ];
    }

    public function tables(): HasMany
    {
        return $this->hasMany(DiningTable::class, 'dine_area_id');
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
