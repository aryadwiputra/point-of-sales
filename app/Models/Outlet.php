<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Outlet extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'is_active',
        'is_sales_enabled',
        'address',
        'phone',
        'email',
        'logo',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_sales_enabled' => 'boolean',
        ];
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_outlets')->withPivot('is_default')->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
