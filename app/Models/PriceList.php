<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriceList extends Model
{
    protected $fillable = ['name', 'slug', 'customer_scope', 'customer_segment_id', 'is_active', 'priority', 'notes'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'priority' => 'integer'];
    }

    public function items() { return $this->hasMany(PriceListItem::class); }
    public function segment() { return $this->belongsTo(CustomerSegment::class); }
    public function scopeActive($q) { $q->where('is_active', true); }
}
