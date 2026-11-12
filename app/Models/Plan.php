<?php

namespace App\Models;

use App\Services\TransactionFeeService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price_monthly',
        'price_yearly',
        'max_outlets',
        'max_users',
        'max_products',
        'max_customers',
        'features',
        'billing_type',
        'fee_type',
        'fee_value',
        'fee_min',
        'fee_max',
        'fee_rounding',
        'fee_methods',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'is_active' => 'boolean',
            'price_monthly' => 'decimal:2',
            'price_yearly' => 'decimal:2',
            'max_outlets' => 'integer',
            'max_users' => 'integer',
            'max_products' => 'integer',
            'max_customers' => 'integer',
            'billing_type' => 'string',
            'fee_value' => 'decimal:2',
            'fee_min' => 'decimal:2',
            'fee_max' => 'decimal:2',
            'fee_methods' => 'array',
        ];
    }

    /** Paket bayar per transaksi (tanpa langganan bulanan/tahunan). */
    public function isPerTransaction(): bool
    {
        return $this->billing_type === TransactionFeeService::BILLING_PER_TRANSACTION;
    }

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(TenantSubscription::class);
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? [], true);
    }
}
