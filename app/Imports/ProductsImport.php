<?php

namespace App\Imports;

use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\ProductImei;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;

class ProductsImport implements ToCollection
{
    public function collection(Collection $rows)
    {
        foreach ($rows->skip(1) as $row) {

            $name = trim($row[0] ?? '');
            $sku = trim($row[1] ?? '');
            $price = $row[2] ?? 0;
            $stock = $row[3] ?? 0;
            $categoryName = trim($row[4] ?? '');
            $brandName = trim($row[5] ?? '');
            $imeis = $row[6] ?? null;

            if (!$name) continue;

            /* ================= CATEGORY ================= */
            $category = Category::firstOrCreate([
                'name' => $categoryName ?: 'Khác'
            ]);

            /* ================= BRAND ================= */
            $brand = Brand::firstOrCreate([
                'name' => $brandName ?: 'No Brand',
                'category_id' => $category->id
            ]);

            /* ================= PRODUCT ================= */
            $product = Product::updateOrCreate(
                ['sku' => $sku ?: Str::slug($name)],
                [
                    'name' => $name,
                    'price' => $price,
                    'stock' => $stock,
                    'category_id' => $category->id,
                    'brand_id' => $brand->id,
                ]
            );

            /* ================= IMEI ================= */
            if ($imeis) {

                $imeiList = explode(',', $imeis);

                foreach ($imeiList as $imei) {

                    $imei = trim($imei);

                    if (!$imei) continue;

                    ProductImei::firstOrCreate([
                        'imei' => $imei
                    ], [
                        'product_id' => $product->id
                    ]);
                }
            }
        }
    }
}