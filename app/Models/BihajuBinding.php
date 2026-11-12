<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Binding outlet BPOS → merchant Bihaju.
 *
 * Dibuat lewat POST /api/partner/bindings/qr, disahkan lewat webhook
 * binding.approved, dan diputus lewat binding.revoked / aksi revoke.
 */
class BihajuBinding extends Model
{
    use BelongsToTenant, HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'tenant_id',
        'outlet_id',
        'external_ref',
        'status',
        'bind_code',
        'bind_url',
        'bind_code_expires_at',
        'merchant_api_key',
        'merchant_id',
        'merchant_name',
        'merchant_city',
        'merchant_status',
        'merchant_balance',
        'last_callback',
        'bound_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'merchant_api_key' => 'encrypted',
            'last_callback' => 'array',
            'bind_code_expires_at' => 'datetime',
            'bound_at' => 'datetime',
            'revoked_at' => 'datetime',
            'merchant_balance' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Kode binding sekali pakai tidak boleh ikut ter-serialize ke client.
        static::creating(function (self $binding): void {
            if (! $binding->external_ref) {
                $binding->external_ref = 'bpos-'.(string) str_replace('.', '', uniqid('', true));
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function bihajuTransactions(): HasMany
    {
        return $this->hasMany(BihajuTransaction::class, 'binding_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE && filled($this->merchant_api_key);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function bindCodeExpired(): bool
    {
        return $this->bind_code_expires_at !== null && $this->bind_code_expires_at->isPast();
    }

    /**
     * @return array<string, mixed>
     */
    public function toClientArray(): array
    {
        return [
            'id' => $this->id,
            'outlet_id' => $this->outlet_id,
            'outlet_name' => $this->outlet?->name,
            'external_ref' => $this->external_ref,
            'status' => $this->status,
            'merchant_id' => $this->merchant_id,
            'merchant_name' => $this->merchant_name,
            'merchant_city' => $this->merchant_city,
            'merchant_balance' => $this->merchant_balance,
            'bound_at' => $this->bound_at?->toIso8601String(),
            'revoked_at' => $this->revoked_at?->toIso8601String(),
            // Bind code hanya dibuka saat masih berlaku dan belum disahkan.
            'bind_code' => $this->isPending() && ! $this->bindCodeExpired()
                ? $this->bind_code
                : null,
            'bind_url' => $this->isPending() && ! $this->bindCodeExpired()
                ? $this->bind_url
                : null,
            'bind_code_expires_at' => $this->isPending()
                ? $this->bind_code_expires_at?->toIso8601String()
                : null,
            'has_api_key' => filled($this->merchant_api_key),
        ];
    }
}
