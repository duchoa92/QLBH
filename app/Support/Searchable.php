<?php

namespace App\Support;

use Illuminate\Support\Str;

trait Searchable
{
    public static function normalizeSearch(
        string $value
    ): string {

        return Str::ascii(
            str_replace(
                'đ',
                'd',
                mb_strtolower($value)
            )
        );
    }
}