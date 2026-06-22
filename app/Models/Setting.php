<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    public const PRODUCT_DISPLAY_IMAGE_GRID = 'image_grid';

    public const PRODUCT_DISPLAY_COMPACT_LIST = 'compact_list';

    public const PRODUCT_DISPLAY_MODES = [
        self::PRODUCT_DISPLAY_IMAGE_GRID,
        self::PRODUCT_DISPLAY_COMPACT_LIST,
    ];

    protected $fillable = [
        'key',
        'value',
        'description',
    ];

    /**
     * Get a setting value by key
     */
    public static function get(string $key, $default = null)
    {
        $setting = static::where('key', $key)->first();

        return $setting ? $setting->value : $default;
    }

    public static function getInt(string $key, int $default = 0): int
    {
        return (int) static::get($key, $default);
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        return filter_var(static::get($key, $default ? '1' : '0'), FILTER_VALIDATE_BOOL);
    }

    public static function productDisplayMode(): string
    {
        $mode = static::get('product_display_mode', self::PRODUCT_DISPLAY_IMAGE_GRID);

        return in_array($mode, self::PRODUCT_DISPLAY_MODES, true)
            ? $mode
            : self::PRODUCT_DISPLAY_IMAGE_GRID;
    }

    /**
     * Set a setting value by key
     */
    public static function set(string $key, $value, ?string $description = null)
    {
        return static::updateOrCreate(
            ['key' => $key],
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
}
