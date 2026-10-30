<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class Setting extends Model
{
    private const CACHE_KEY = 'settings.all';

    protected $fillable = ['key', 'value'];

    /** Cache the schema check so an unavailable database is only probed once per request. */
    protected static ?bool $settingsTableAvailable = null;

    /** Keep the complete setting map in memory after the first lookup of a request. */
    protected static ?array $settingsCache = null;

    // Lấy setting
    public static function get($key, $default = null)
    {
        if (static::$settingsTableAvailable === null) {
            try {
                static::$settingsTableAvailable = Schema::hasTable('settings');
            } catch (\Throwable $exception) {
                static::$settingsTableAvailable = false;
            }
        }

        if (! static::$settingsTableAvailable) {
            return $default;
        }

        if (static::$settingsCache === null) {
            static::$settingsCache = Cache::rememberForever(
                self::CACHE_KEY,
                fn (): array => self::query()->pluck('value', 'key')->all()
            );
        }

        return array_key_exists($key, static::$settingsCache)
            ? static::$settingsCache[$key]
            : $default;
    }

    // Set setting
    public static function set($key, $value)
    {
        $setting = self::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );

        static::$settingsCache = null;
        Cache::forget(self::CACHE_KEY);
        Cache::forget("setting_$key");

        return $setting;
    }
}
