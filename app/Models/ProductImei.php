<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Product;

class ProductImei extends Model
{

    public const STATUS_AVAILABLE = 0;

    public const STATUS_SOLD = 1;

    public const STATUS_REPAIRING = 2;

    public const STATUS_RETURNED = 3;



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

    // Thêm quan hệ với Product
    public function product(): BelongsTo
    {
        return $this->belongsTo(
            Product::class
        );
    }

    // Thêm quan hệ với SaleItem
    public function saleItems()
    {
        return $this->hasMany(
            SaleItem::class,
            'product_imei_id'
        );
    }
}