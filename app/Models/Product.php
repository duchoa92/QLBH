<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

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
        'has_imei',
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

    public function brand()
    {
        return $this->belongsTo(
            Brand::class
        );
    }

    public function unit()
    {
        return $this->belongsTo(
            Unit::class
        );
    }

    public function imeis()
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
}