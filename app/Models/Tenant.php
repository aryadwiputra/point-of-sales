<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'subdomain',
        'custom_domain',
        'plan_id',
        'owner_id',
        'status',
        'trial_ends_at',
        'subscription_ends_at',
        'wa_session_id',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'trial_ends_at' => 'datetime',
            'subscription_ends_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(TenantSubscription::class);
    }

    public function latestSubscription(): HasOne
    {
        return $this->hasOne(TenantSubscription::class)->latestOfMany();
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function outlets(): HasMany
    {
        return $this->hasMany(Outlet::class);
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function addons(): HasMany
    {
        return $this->hasMany(TenantAddon::class);
    }

    /**
     * Add-on aktif milik tenant ini (finance, bihaju, ...).
     */
    public function activeAddons(): HasMany
    {
        return $this->addons()->where('status', TenantAddon::STATUS_ACTIVE);
    }

    public function hasAddon(string $code): bool
    {
        return $this->addons()
            ->where('code', $code)
            ->get()
            ->contains(fn (TenantAddon $addon) => $addon->isActive());
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function isActive(): bool
    {
        if ($this->status === 'active') {
            return ! $this->subscription_ends_at || $this->subscription_ends_at->isFuture();
        }

        if ($this->status === 'trial') {
            return ! $this->trial_ends_at || $this->trial_ends_at->isFuture();
        }

        return false;
    }

    public function isTrial(): bool
    {
        return $this->status === 'trial' && (! $this->trial_ends_at || $this->trial_ends_at->isFuture());
    }

    public function isExpired(): bool
    {
        return ! $this->isActive();
    }

    public function hasFeature(string $feature): bool
    {
        return $this->plan ? $this->plan->hasFeature($feature) : false;
    }
}
