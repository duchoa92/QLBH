<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    // Lấy setting
    public static function get($key, $default = null)
    {
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
