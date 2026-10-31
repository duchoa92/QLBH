<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItemGift extends Model
{
    protected $fillable = [

        'sale_item_id',

        'product_id',

        'variant_id',

        'product_imei_id',

        'quantity',

        'unit_cost',

        'base_quantity',

        'conversion_factor',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_cost' => 'decimal:2',
        'base_quantity' => 'integer',
        'conversion_factor' => 'integer',
    ];

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(
            SaleItem::class
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

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
