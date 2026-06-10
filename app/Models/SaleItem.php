<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    protected $fillable = [

        'sale_id',

        'product_id',

        'product_imei_id',

        'quantity',

        'unit_price',

        'subtotal',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public function sale(): BelongsTo
    {
        return $this->belongsTo(
            Sale::class
        );
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(
            Product::class
        );
    }

    public function productImei(): BelongsTo
    {
        return $this->belongsTo(
            ProductImei::class,
            'product_imei_id'
        );
    }
}