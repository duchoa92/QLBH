<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockImport extends Model
{
    use HasFactory;

    protected $table = 'stock_imports';

    /**
     * Các thuộc tính có thể gán hàng loạt (Mass Assignment).
     */
    protected $fillable = [
        'code',
        'supplier_id',
        'user_id',
        'import_date',
        'discount',
        'extra_fee',
        'total_amount',
        'grand_total',
        'note',
        'status',
    ];

    /**
     * Tự động ép kiểu dữ liệu khi lấy ra/lưu vào DB.
     */
    protected $casts = [
        'import_date'  => 'datetime',
        'discount'     => 'decimal:2',
        'extra_fee'    => 'decimal:2',
        'total_amount' => 'decimal:2',
        'grand_total'  => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | QUAN HỆ ELOQUENT (RELATIONSHIPS)
    |--------------------------------------------------------------------------
    */

    /**
     * Đơn nhập hàng thuộc về một Nhà cung cấp.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    /**
     * Đơn nhập hàng do một Nhân viên/User tạo.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Một đơn nhập hàng có nhiều Chi tiết sản phẩm nhập kho.
     */
    public function items(): HasMany
    {
        return $this->hasMany(StockImportItem::class, 'stock_import_id');
    }
}