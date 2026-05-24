<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\ProductImei;

class SaleItem extends Model
{
    // Các trường có thể gán hàng loạt
    protected $fillable = [

         'sale_id',

        'product_id',

        'product_imei_id',

        'quantity',

        'unit_price',

        'discount',

        'tax',

        'subtotal',
    ];

    // Lấy thông tin đơn hàng
    public function sale(): BelongsTo
    {
        return $this->belongsTo(
            Sale::class
        );
    }

    // Lấy thông tin sản phẩm
    public function product(): BelongsTo
    {
        return $this->belongsTo(
            Product::class
        );
    }

    // Lấy thông tin IMEI nếu có
    public function imei(): BelongsTo
    {
        return $this->belongsTo(
            ProductImei::class,
            'product_imei_id'
        );
    }

    // Alias cho productImei
    public function productImei(): BelongsTo
    {
        return $this->belongsTo(
            ProductImei::class
        );
    }
}