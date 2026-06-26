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

        'quantity',
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
}