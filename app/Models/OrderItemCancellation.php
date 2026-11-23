<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Một sản phẩm đã bị hủy riêng lẻ khỏi đơn bán (Sale) hoặc đơn nhập (StockImport). */
class OrderItemCancellation extends Model
{
    protected $fillable = [
        'order_type',
        'order_id',
        'product_id',
        'product_name',
        'variant_label',
        'imeis',
        'quantity',
        'unit_name',
        'unit_price',
        'amount',
        'meta',
        'reason',
        'user_id',
        'cancelled_at',
    ];

    protected $casts = [
        'imeis' => 'array',
        'meta' => 'array',
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'amount' => 'decimal:2',
        'cancelled_at' => 'datetime',
    ];

    public function order(): MorphTo
    {
        return $this->morphTo('order');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Tên biến thể dạng "Màu: Đen / Bộ nhớ: 128GB" từ cột attributes của biến thể. */
    public static function variantLabel(?ProductVariant $variant): ?string
    {
        if (! $variant) {
            return null;
        }

        $attributes = $variant->attributes;
        if (is_array($attributes) && $attributes !== []) {
            return collect($attributes)
                ->map(fn ($value, $key) => is_string($key) ? "{$key}: {$value}" : (string) $value)
                ->implode(' / ');
        }

        return $variant->sku ?: null;
    }
}
