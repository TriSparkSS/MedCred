<?php

namespace App\Helpers;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SettingHelper
{
    protected static function cacheKey(): string
    {
        return 'settings.central';
    }

    /**
     * Get a single setting
     */
    public static function get(string $key, $default = null)
    {
        $settings = self::all();

        return $settings[$key] ?? $default;
    }

    /**
     * Get all settings (SAFE for file/database/redis)
     */
    public static function all(): array
    {
        return Cache::store(config('cache.default'))->rememberForever(
            self::cacheKey(),
            fn () => Setting::pluck('value', 'key')->toArray()
        );
    }

    /**
     * Clear only current tenant cache
     */
    public static function clearCache(): void
    {
        Cache::store(config('cache.default'))->forget(self::cacheKey());
    }
}
