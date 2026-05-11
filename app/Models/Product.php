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

        'cost_price',

        'sell_price',

        'stock',

        'alert_stock',

        'has_imei',

        'is_active',

        'description',

    ];

    
     protected $casts = [
        'cost_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'is_active' => 'boolean',
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
}