<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class BankAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'bank_name',
        'outlet_id',
        'account_number',
        'account_name',
        'logo',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'outlet_id' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $appends = [
        'logo_url',
    ];

    /**
     * Scope to get only active bank accounts
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForOutlet(Builder $query, ?Outlet $outlet): Builder
    {
        if (! $outlet) {
            return $query->whereNull('outlet_id');
        }

        return $query->where(function (Builder $query) use ($outlet) {
            $query->whereNull('outlet_id')->orWhere('outlet_id', $outlet->id);
        });
    }

    /**
     * Scope to order by sort_order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('bank_name');
    }

    /**
     * Get transactions using this bank account
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo ? Storage::disk('public')->url($this->logo) : null;
    }
}
