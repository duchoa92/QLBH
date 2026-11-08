<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use App\Support\Searchable;
use App\Traits\PreventDeleteIfHasRelations;


class Brand extends Model
{
    use SoftDeletes, PreventDeleteIfHasRelations, Searchable;

    // Thương hiệu dùng chung: danh mục nằm ở bảng trung gian brand_category.
    // Cột brands.category_id cũ không còn được dùng.
    protected $fillable = [

        'name',

        'slug',

        'sort_order',

        'is_active',

    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function products()
    {
        return $this->hasMany(
            Product::class
        );
    }

    protected static function booted(): void
    {
        static::saving(function (
            Brand $brand
        ): void {

            $brand->search_text =
                self::normalizeSearch(
                    $brand->name
                );
        });
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'brand_category');
    }

    /**
     * Danh sách thương hiệu cho ô chọn: [{ id, name, category_ids: [...] }].
     * Có thể giới hạn theo một danh mục và/hoặc chỉ lấy thương hiệu đang hoạt động.
     */
    public static function optionsWithCategories(?int $categoryId = null, bool $onlyActive = false): Collection
    {
        return static::query()
            ->with('categories:id')
            ->when($onlyActive, fn ($q) => $q->where('is_active', true))
            ->when($categoryId, fn ($q) => $q->whereHas(
                'categories',
                fn ($c) => $c->where('categories.id', $categoryId)
            ))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Brand $brand) => [
                'id' => $brand->id,
                'name' => $brand->name,
                'category_ids' => $brand->categories->pluck('id')->values()->all(),
            ])
            ->values();
    }

}