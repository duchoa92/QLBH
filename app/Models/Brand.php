<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Support\Searchable;

class Brand extends Model
{
    use SoftDeletes, Searchable;

    protected $fillable = [

        'name',

        'slug',

        'sort_order',

        'is_active',

    ];

    public function products()
    {
        return $this->hasMany(
            Product::class
        );
    }

    protected static function booted(): void
    {
        static::saving(function (
            Brand $brand
        ): void {

            $brand->search_text =
                self::normalizeSearch(
                    $brand->name
                );
        });
    }

}