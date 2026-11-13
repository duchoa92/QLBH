<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductStockBatch extends Model
{
    protected $fillable = [
        'product_id', 'variant_id', 'stock_import_item_id', 'initial_quantity',
        'remaining_quantity', 'cost_price', 'sell_price', 'imported_at', 'is_opening_balance', 'is_used',
    ];

    protected $casts = [
        'initial_quantity' => 'integer',
        'remaining_quantity' => 'integer',
        'cost_price' => 'decimal:2',
        'sell_price' => 'decimal:2',
        'imported_at' => 'date',
        'is_opening_balance' => 'boolean',
        'is_used' => 'boolean',
    ];

    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function variant(): BelongsTo { return $this->belongsTo(ProductVariant::class); }
    public function stockImportItem(): BelongsTo { return $this->belongsTo(StockImportItem::class); }
    public function allocations(): HasMany { return $this->hasMany(SaleItemStockBatch::class); }
}
