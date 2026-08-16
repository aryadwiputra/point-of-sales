<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class DiningTable extends Model
{
    use HasFactory;

    protected $table = 'dine_tables';

    protected $fillable = [
        'dine_area_id',
        'name',
        'token',
        'capacity',
        'pos_x',
        'pos_y',
        'shape',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'pos_x' => 'integer',
            'pos_y' => 'integer',
            'capacity' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (DiningTable $table) {
            if (empty($table->token)) {
                $table->token = (string) Str::uuid();
            }
        });
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(DineArea::class, 'dine_area_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(DineOrder::class, 'dine_table_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
