<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RepairPart extends Model
{
    protected $fillable = [
        'repair_id', 'product_id', 'variant_id', 'product_name', 'sku',
        'quantity', 'unit_price', 'subtotal',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function repair(): BelongsTo
    {
        return $this->belongsTo(Repair::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    /** Các lô FIFO mà linh kiện này đã lấy hàng (dùng để hoàn lô khi xóa phiếu). */
    public function stockBatchAllocations(): HasMany
    {
        return $this->hasMany(SaleItemStockBatch::class, 'repair_part_id');
    }
}
