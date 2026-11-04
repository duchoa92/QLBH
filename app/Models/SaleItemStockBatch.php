<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItemStockBatch extends Model
{
    protected $fillable = ['sale_item_id', 'sale_item_gift_id', 'product_stock_batch_id', 'quantity', 'unit_cost'];

    protected $casts = ['quantity' => 'integer', 'unit_cost' => 'decimal:2'];

    public function batch(): BelongsTo { return $this->belongsTo(ProductStockBatch::class, 'product_stock_batch_id'); }
    public function saleItem(): BelongsTo { return $this->belongsTo(SaleItem::class); }
    public function saleItemGift(): BelongsTo { return $this->belongsTo(SaleItemGift::class); }
}
