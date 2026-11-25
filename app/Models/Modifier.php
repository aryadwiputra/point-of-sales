<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Modifier extends Model
{
    use HasFactory;

    protected $fillable = [
        'modifier_group_id', 'name', 'price_delta', 'is_default', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'modifier_group_id' => 'integer',
        'price_delta' => 'integer',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function group()
    {
        return $this->belongsTo(ModifierGroup::class, 'modifier_group_id');
    }

    public function overrides()
    {
        return $this->hasMany(ProductModifierOverride::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
