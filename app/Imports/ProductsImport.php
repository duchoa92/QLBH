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
        $header = $products->first()->map(fn($h) => strtolower(trim($h)))->toArray();

        foreach ($products->skip(1) as $row) {

            $data = array_combine($header, $row->toArray());

            $name = trim($data['name'] ?? '');
            $sku  = trim($data['sku'] ?? '');

            if (!$name || !$sku) continue;

            // ✅ dùng header hết
            $category = Category::where('name', trim($data['category'] ?? ''))->first();
            $brand    = Brand::where('name', trim($data['brand'] ?? ''))->first();

            Product::withTrashed()->updateOrCreate(
                ['sku' => $sku],
                [
                    'name' => $name,
                    'slug' => Str::slug($name) . '-' . uniqid(),

                    'barcode'      => $data['barcode'] ?? null,
                    'product_type' => $data['product_type'] ?? 'normal',
                    'cost_price'   => $data['cost_price'] ?? 0,
                    'sell_price'   => $data['sell_price'] ?? 0,
                    'stock'        => $data['stock'] ?? 0,

                    'category_id' => $category?->id,
                    'brand_id'    => $brand?->id,

                    'is_active'  => $data['is_active'] ?? 1,
                    'image_name' => $data['image'] ?? null,
                ]
            );
        }
    }
}