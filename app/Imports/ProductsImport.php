<?php

use Maatwebsite\Excel\Concerns\ToCollection;
use Illuminate\Support\Collection;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use Illuminate\Support\Str;

class ProductsImport implements ToCollection
{
    public function collection(Collection $sheets)
    {
        $products = $sheets[0];
        $categories = $sheets[1];
        $brands = $sheets[2];

        /* ========= CATEGORY ========= */
        foreach ($categories->skip(1) as $row) {

            $name = trim($row[0]);
            $parentName = trim($row[1]);

            if (!$name) continue;

            $parent = Category::where('name', $parentName)->first();

            Category::updateOrCreate(
                ['name' => $name],
                [
                    'slug' => Str::slug($name),
                    'parent_id' => $parent?->id,
                    'is_active' => $row[2] ?? 1,
                ]
            );
        }

        /* ========= BRAND ========= */
        foreach ($brands->skip(1) as $row) {

            $name = trim($row[0]);
            $categoryName = trim($row[1]);

            if (!$name) continue;

            $category = Category::where('name', $categoryName)->first();

            Brand::updateOrCreate(
                ['name' => $name],
                [
                    'slug' => Str::slug($name),
                    'category_id' => $category?->id,
                    'is_active' => $row[2] ?? 1,
                ]
            );
        }

        /* ========= PRODUCT ========= */
        foreach ($products->skip(1) as $row) {

            $name = trim($row[0]);
            $sku = trim($row[1]);

            if (!$name || !$sku) continue;

            $category = Category::where('name', trim($row[7]))->first();
            $brand = Brand::where('name', trim($row[8]))->first();

            Product::withTrashed()->updateOrCreate(
                ['sku' => $sku],
                [
                    'name' => $name,
                    'slug' => Str::slug($name) . '-' . uniqid(),
                    'barcode' => $row[2] ?? null,
                    'product_type' => $row[3] ?? 'normal',
                    'cost_price' => $row[4] ?? 0,
                    'sell_price' => $row[5] ?? 0,
                    'stock' => $row[6] ?? 0,
                    'category_id' => $category?->id,
                    'brand_id' => $brand?->id,
                    'is_active' => $row[9] ?? 1,
                ]
            );
        }
    }
}