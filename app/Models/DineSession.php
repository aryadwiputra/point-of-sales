<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DineSession extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    protected $attributes = [
        'status' => self::STATUS_OPEN,
    ];

    protected $fillable = [
        'dine_table_id',
        'status',
        'guest_count',
        'opened_by',
        'opened_at',
        'closed_at',
        'merged_into_session_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'guest_count' => 'integer',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'dine_table_id');
    }

    public function bills(): HasMany
    {
        return $this->hasMany(DineBill::class);
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(DineSession::class, 'merged_into_session_id');
    }

    public function mergedSessions(): HasMany
    {
        return $this->hasMany(DineSession::class, 'merged_into_session_id');
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_OPEN);
    }
}
