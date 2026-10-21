<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /** Cache the schema check so an unavailable database is only probed once per request. */
    protected static ?bool $settingsTableAvailable = null;

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

        return Cache::rememberForever("setting_$key", function () use ($key, $default) {
            return self::where('key', $key)->value('value') ?? $default;
        });
    }

    // Set setting
    public static function set($key, $value)
    {
        Cache::forget("setting_$key");

        return self::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }
}
