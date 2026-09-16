<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockImportItem extends Model
{
    protected $fillable = [
        'stock_import_id',
        'product_id',
        'variant_id',
        'unit_id',
        'unit_name',
        'quantity',
        'cost_price',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'cost_price' => 'decimal:2',
    ];

    public function stockImport(): BelongsTo
    {
        return $this->belongsTo(
            StockImport::class,
            'stock_import_id'
        );
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(
            Product::class,
            'product_id'
        );
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(
            ProductVariant::class,
            'variant_id'
        );
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(
            Unit::class,
            'unit_id'
        );
    }
}
