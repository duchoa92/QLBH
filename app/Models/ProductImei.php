<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Product;

class ProductImei extends Model
{

    public const STATUS_IN_STOCK = 'in_stock';

    public const STATUS_AVAILABLE = self::STATUS_IN_STOCK;

    public const STATUS_SOLD = 'sold';




    use SoftDeletes;

    protected $fillable = [

        'product_id',

        'variant_id',

        'supplier_id',

        'imei',

        'serial',

        'color',

        'storage',

        'extra_info',

        'cost_price',

        'sell_price',

        'warranty_expired_at',

        'customer_warranty_expires_at',
        'customer_warranty_days',
        'customer_warranty_voided_at',
        'customer_warranty_void_reason',
        'customer_warranty_voided_by',

        'status',

        'sold_at',

        'imported_at',

        'note',

    ];

    protected $casts = [
        'extra_info' => 'array',
        'warranty_expired_at' => 'datetime',
        'customer_warranty_expires_at' => 'datetime',
        'customer_warranty_days' => 'integer',
        'customer_warranty_voided_at' => 'datetime',
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

    // Quan hệ với Variant
    public function variant()
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(ProductImeiHistory::class);
    }
}
