<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SiteConfig extends Model
{
    protected $fillable = ['key', 'value', 'description'];

    /**
     * Get a config value by key, with optional default.
     */
    public static function getValue(string $key, ?string $default = null): ?string
    {
        return Cache::remember("site_config.{$key}", 300, function () use ($key, $default) {
            $config = static::where('key', $key)->first();

            return $config?->value ?? $default;
        });
    }

    /**
     * Set a config value by key (creates if missing).
     */
    public static function setValue(string $key, ?string $value): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );

        Cache::forget("site_config.{$key}");
    }
}
