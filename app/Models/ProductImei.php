<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductImei extends Model
{
    use SoftDeletes;

    protected $fillable = [

        'product_id',

        'imei',

        'serial',

        'color',

        'storage',

        'cost_price',

        'sell_price',

        'status',

        'sold_at',

        'note',

    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}