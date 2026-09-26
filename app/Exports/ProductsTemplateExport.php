<?php

namespace App\Exports;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Unit;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ProductsTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new ProductsTemplateSheet(),
            new ProductReferenceSheet(
                'Danh mục',
                ['ID', 'Tên danh mục', 'Trạng thái'],
                Category::query()
                    ->orderBy('name')
                    ->get(['id', 'name', 'is_active'])
                    ->map(fn (Category $category) => [
                        $category->id,
                        $category->name,
                        $category->is_active ? 'Đang dùng' : 'Tạm tắt',
                    ])
            ),
            new ProductReferenceSheet(
                'Thương hiệu',
                ['ID', 'Tên thương hiệu', 'Danh mục', 'Trạng thái'],
                Brand::query()
                    ->with('category:id,name')
                    ->orderBy('name')
                    ->get(['id', 'name', 'category_id', 'is_active'])
                    ->map(fn (Brand $brand) => [
                        $brand->id,
                        $brand->name,
                        $brand->category?->name,
                        $brand->is_active ? 'Đang dùng' : 'Tạm tắt',
                    ])
            ),
            new ProductReferenceSheet(
                'Đơn vị tính',
                ['ID', 'Tên đơn vị', 'Tên ngắn', 'Trạng thái'],
                Unit::query()
                    ->orderBy('name')
                    ->get(['id', 'name', 'short_name', 'is_active'])
                    ->map(fn (Unit $unit) => [
                        $unit->id,
                        $unit->name,
                        $unit->short_name,
                        $unit->is_active ? 'Đang dùng' : 'Tạm tắt',
                    ])
            ),
        ];
    }
}
