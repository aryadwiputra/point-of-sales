<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'outlet_id',
        'value',
        'description',
    ];

    protected static ?bool $hasOutletColumn = null;

    protected static function booted(): void
    {
        static::saved(function (self $setting): void {
            static::forgetCached($setting->key, $setting->outlet_id);
        });

        static::deleted(function (self $setting): void {
            static::forgetCached($setting->key, $setting->outlet_id);
        });
    }

    /**
     * Get a setting value by key
     */
    public static function get(string $key, $default = null)
    {
        $value = static::remember($key, null, function () use ($key) {
            $query = static::where('key', $key);
            if (static::hasOutletColumn()) {
                $query->whereNull('outlet_id');
            }

            return $query->value('value');
        });

        return $value ?? $default;
    }

    public static function getInt(string $key, int $default = 0): int
    {
        return (int) static::get($key, $default);
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        return filter_var(static::get($key, $default ? '1' : '0'), FILTER_VALIDATE_BOOL);
    }

    public static function getForOutlet(string $key, ?Outlet $outlet, $default = null)
    {
        if ($outlet) {
            if (! static::hasOutletColumn()) {
                return static::get($key, $default);
            }

            $value = static::remember($key, $outlet->id, function () use ($key, $outlet) {
                return static::where('key', $key)->where('outlet_id', $outlet->id)->value('value');
            });

            if ($value !== null) {
                return $value;
            }
        }

        return static::get($key, $default);
    }

    public static function getBoolForOutlet(string $key, ?Outlet $outlet, bool $default = false): bool
    {
        return filter_var(static::getForOutlet($key, $outlet, $default ? '1' : '0'), FILTER_VALIDATE_BOOL);
    }

    public static function setForOutlet(string $key, $value, ?Outlet $outlet, ?string $description = null)
    {
        if (! $outlet) {
            return static::set($key, $value, $description);
        }

        if (! static::hasOutletColumn()) {
            return static::set($key, $value, $description);
        }

        return static::updateOrCreate(
            ['key' => $key, 'outlet_id' => $outlet->id],
            ['value' => $value, 'description' => $description]
        );
    }

    /**
     * Set a setting value by key
     */
    public static function set(string $key, $value, ?string $description = null)
    {
        $attributes = ['key' => $key];
        if (static::hasOutletColumn()) {
            $attributes['outlet_id'] = null;
        }

        return static::updateOrCreate(
            $attributes,
            ['value' => $value, 'description' => $description]
        );
    }

    public static function setMany(array $settings): void
    {
        foreach ($settings as $key => $payload) {
            static::set(
                $key,
                $payload['value'] ?? null,
                $payload['description'] ?? null
            );
        }
    }

    /**
     * Drop the cached value for a setting.
     */
    public static function forgetCached(string $key, $outletId = null): void
    {
        Cache::forget(static::cacheKey($key, $outletId));
    }

    protected static function remember(string $key, $outletId, callable $resolver)
    {
        return Cache::rememberForever(
            static::cacheKey($key, $outletId),
            fn () => ['value' => $resolver()]
        )['value'];
    }

    protected static function cacheKey(string $key, $outletId = null): string
    {
        return 'setting:'.$key.':'.($outletId ?: 0);
    }

    protected static function hasOutletColumn(): bool
    {
        if (static::$hasOutletColumn === true) {
            return true;
        }

        return static::$hasOutletColumn = Schema::hasColumn('settings', 'outlet_id');
    }
}
