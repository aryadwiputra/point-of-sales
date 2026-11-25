<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModifierGroup extends Model
{
    use HasFactory;

    public const SELECTION_SINGLE = 'single';

    public const SELECTION_MULTIPLE = 'multiple';

    protected $fillable = [
        'name', 'selection_type', 'min_select', 'max_select', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'min_select' => 'integer',
        'max_select' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function modifiers()
    {
        return $this->hasMany(Modifier::class)->orderBy('sort_order')->orderBy('id');
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_modifier_groups')
            ->withPivot(['sort_order', 'is_active'])
            ->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function isSingle(): bool
    {
        return $this->selection_type === self::SELECTION_SINGLE;
    }
}
