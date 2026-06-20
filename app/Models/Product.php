<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Support\Searchable;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use SoftDeletes, Searchable;

    protected $fillable = [

        'category_id',
        'brand_id',
        'unit_id',
        'name',
        'slug',
        'sku',
        'barcode',
        'image',
        'cost_price',
        'sell_price',
        'stock',
        'alert_stock',
        'product_type',
        'is_active',
        'description',

    ];

    
     protected $casts = [
        'cost_price' => 'float',

        'sell_price' => 'float',

        'tax_percent' => 'float',

        'is_active' => 'boolean',

        'allow_negative_stock' => 'boolean',
    ];


    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

     public function category(): BelongsTo
    {
        return $this->belongsTo(
            Category::class
        );
    }

   public function brand(): BelongsTo
    {
        return $this->belongsTo(
            Brand::class
        );
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(
            Unit::class
        );
    }

    public function imeis(): HasMany
    {
        return $this->hasMany(
            ProductImei::class
        );
    }

    // Accessors image_url
    protected $appends = [
        'image_url',
    ];

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image) {
            return null;
        }

        return asset(
            'storage/' . $this->image
        );
    }

    protected static function booted(): void
    {
        static::saving(function (
            Product $product
        ): void {

            $product->search_text =
                self::normalizeSearch(
                    $product->name ?? ''
                );

        });
    }

    public function scopeSearch(
        $query,
        string $keyword
    )
    {
        $normalizedKeyword =
            self::normalizeSearch(
                $keyword
            );

        return $query->where(function ($sub)
        use (
            $keyword,
            $normalizedKeyword
        ) {

            $sub
                ->where('barcode', $keyword)

                ->orWhere(
                    'sku',
                    'like',
                    $keyword . '%'
                )

                ->orWhere(
                    'search_text',
                    'like',
                    '%' .
                    $normalizedKeyword .
                    '%'
                );
        });
    }

}