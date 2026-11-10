<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Support\Searchable;
use Illuminate\Database\Eloquent\Relations\HasMany;


class Product extends Model
{
    use SoftDeletes, Searchable;

    protected $fillable = [

        'category_id',
        'brand_id',
        'unit_id',
        'has_unit_conversion',
        'conversion_unit_id',
        'conversion_factor',
        'name',
        'slug',
        'sku',
        'model',
        'image',
        'cost_price',
        'sell_price',
        'stock',
        'alert_stock',
        'product_type',
        'manage_stock_by_serial',
        'warranty_days',
        'is_active',
        'description',

    ];

    
     protected $casts = [
        'cost_price' => 'float',

        'sell_price' => 'float',

        'tax_percent' => 'float',

        'is_active' => 'boolean',

        'allow_negative_stock' => 'boolean',

        'manage_stock_by_serial' => 'boolean',

        'has_unit_conversion' => 'boolean',

        'conversion_factor' => 'integer',

        'warranty_days' => 'integer',
    ];

    protected $attributes = [
        'is_active' => true
    ];


    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

     public function category(): BelongsTo
    {
        return $this->belongsTo(
            Category::class
        );
    }

   public function brand(): BelongsTo
    {
        return $this->belongsTo(
            Brand::class
        );
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(
            Unit::class
        );
    }

    public function conversionUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'conversion_unit_id');
    }

    public function stockBatches(): HasMany
    {
        return $this->hasMany(ProductStockBatch::class);
    }

    public function inventoryConversionFactor(): int
    {
        return $this->has_unit_conversion
            ? max(1, (int) $this->conversion_factor)
            : 1;
    }

    public function imeis(): HasMany
    {
        return $this->hasMany(
            ProductImei::class
        );
    }

    // Accessors image_url
    protected $appends = [
        'image_url',
    ];

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image) {
            return null;
        }

        return '/product-images/' . rawurlencode(basename((string) $this->image));
    }

    protected static function booted()
    {
        static::saving(function ($product) {
            $product->search_text = strtolower(
            $product->name . ' ' .
            $product->sku . ' ' .
            optional($product->category)->name . ' ' .
            optional($product->brand)->name
        );
        });
    }

    public function scopeSearch(
        $query,
        string $keyword
    )
    {
        $normalizedKeyword =
            self::normalizeSearch(
                $keyword
            );

        return $query->where(function ($sub)
        use (
            $keyword,
            $normalizedKeyword
        ) {

            $sub
                ->where('model', $keyword)

                ->orWhere(
                    'sku',
                    'like',
                    $keyword . '%'
                )

                ->orWhere(
                    'search_text',
                    'like',
                    '%' .
                    $normalizedKeyword .
                    '%'
                );
        });
    }

    /** Các vị trí đang giữ lịch sử của sản phẩm, không được xóa vĩnh viễn. */
    public function deletionUsage(): array
    {
        return [
            'sale_items' => \DB::table('sale_items')->where('product_id', $this->id)->count(),
            'gift_items' => \DB::table('sale_item_gifts')->where('product_id', $this->id)->count(),
            'import_items' => \DB::table('stock_import_items')->where('product_id', $this->id)->count(),
            'imeis' => \DB::table('product_imeis')->where('product_id', $this->id)->count(),
            'repair_parts' => \DB::table('repair_parts')->where('product_id', $this->id)->count(),
        ];
    }

    public function canForceDelete(): bool
    {
        return collect($this->deletionUsage())->sum() === 0;
    }

    // Biến thể
    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

}
