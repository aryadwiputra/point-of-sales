<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantAddon extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_SUSPENDED = 'suspended';

    /** @var array<int, string> */
    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
        self::STATUS_SUSPENDED,
    ];

    protected $fillable = [
        'tenant_id',
        'code',
        'status',
        'config',
        'secret',
        'activated_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'secret' => 'encrypted',
            'activated_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isActive(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /** Ambil satu nilai konfigurasi dengan default. */
    public function config(string $key, mixed $default = null): mixed
    {
        return data_get($this->config ?? [], $key, $default);
    }

    public function putConfig(string $key, mixed $value): void
    {
        $config = $this->config ?? [];
        data_set($config, $key, $value);
        $this->config = $config;
    }
}
