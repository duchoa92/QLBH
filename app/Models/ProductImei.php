<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Product;
use App\Support\WarrantyDuration;

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
        'is_used',

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
        'is_used' => 'boolean',
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

    public function giftSaleItems(): HasMany
    {
        return $this->hasMany(SaleItemGift::class, 'product_imei_id');
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

    /**
     * BH khách ghi lúc nhập máy này, quy ra ngày; null nếu phiếu nhập không có.
     * Ưu tiên BH riêng của IMEI, không có thì lấy BH của dòng nhập đã nhập IMEI này
     * (IMEI nhập hàng loạt trước đây chỉ lưu mã, BH nằm ở dòng nhập).
     */
    public function importWarrantyDays(): ?int
    {
        $own = WarrantyDuration::daysFromImportExtra($this->extra_info);
        if ($own !== null) {
            return $own;
        }

        $history = ProductImeiHistory::query()
            ->where('product_imei_id', $this->id)
            ->where('type', 'import')
            ->latest('id')
            ->first();

        $itemId = (int) ($history?->meta['stock_import_item_id'] ?? 0);
        if ($itemId <= 0) {
            return null;
        }

        $item = StockImportItem::query()->find($itemId, ['id', 'extra_info']);

        return $item ? WarrantyDuration::daysFromImportExtra($item->extra_info) : null;
    }
}
