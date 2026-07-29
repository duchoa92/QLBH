<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductImportService
{

    public function handle(array $rows, $allowDuplicate = false, $images = [])
    {
        $errors = [];
        $success = 0;

        DB::beginTransaction();
            
        $skuList = [];
        $imageFiles = [];

        try {
             
            foreach ($images as $file) {
                $key = strtolower(
                    preg_replace('/[^a-z0-9]/', '', pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
                );

                $imageFiles[$key] = $file;
            }

            foreach ($rows as $index => $row) {

                if ($index === 0) continue;
                if (empty(array_filter($row))) continue;

                $name         = trim($row[1] ?? '');
                $sku          = trim($row[2] ?? '');
                $barcode      = trim($row[3] ?? '');
                $categoryName = trim($row[4] ?? '');
                $brandName    = trim($row[5] ?? '');
                $sellPrice    = $row[6] ?? 0;
                $type         = $row[7] ?? 'normal';
                $active       = $row[8] ?? 1;
                $rawImageName = trim($row[9] ?? '');

                $imageName = strtolower(
                    preg_replace('/[^a-z0-9]/', '', pathinfo($rawImageName, PATHINFO_FILENAME))
                );

                $rowNumber = $index + 1;



                /* ========= VALIDATE ========= */

                if (!$name) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'name' => $name,
                        'sku' => $sku,
                        'barcode' => $barcode,
                        'category' => $categoryName,
                        'brand' => $brandName,
                        'sell_price' => $sellPrice,
                        'type' => $type,
                        'active' => $active,
                        'error' => 'Thiếu tên sản phẩm'
                    ];
                    continue;
                }

                if (!$sku) {
                     $errors[] = [
                        'row' => $rowNumber,
                        'name' => $name,
                        'sku' => $sku,
                        'barcode' => $barcode,
                        'category' => $categoryName,
                        'brand' => $brandName,
                        'sell_price' => $sellPrice,
                        'type' => $type,
                        'active' => $active,
                        'error' => 'Thiếu SKU'
                    ];
                    continue;
                }

                if (!is_numeric($sellPrice)) {
                     $errors[] = [
                        'row' => $rowNumber,
                        'name' => $name,
                        'sku' => $sku,
                        'barcode' => $barcode,
                        'category' => $categoryName,
                        'brand' => $brandName,
                        'sell_price' => $sellPrice,
                        'type' => $type,
                        'active' => $active,
                        'error' => 'Giá bán không hợp lệ'
                    ];
                    continue;
                }

                /* ========= CHECK TRÙNG SKU TRONG FILE ========= */

                if (in_array($sku, $skuList)) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'name' => $name,
                        'sku' => $sku,
                        'barcode' => $barcode,
                        'category' => $categoryName,
                        'brand' => $brandName,
                        'sell_price' => $sellPrice,
                        'type' => $type,
                        'active' => $active,
                        'image_name' => $rawImageName, // 🔥 FIX
                        'error' => 'Trùng SKU trong file'
                    ];
                    continue;
                }

                $skuList[] = $sku;


                /* ========= CATEGORY ========= */

                $category = null;

                if ($categoryName) {
                    $category = Category::whereRaw('LOWER(name) = ?', [
                        strtolower($categoryName)
                    ])->first();

                    if (!$category) {
                        $category = Category::create([
                            'name' => $categoryName,
                            'slug' => Str::slug($categoryName),
                            'is_active' => 1
                        ]);
                    }
                }

                /* ========= BRAND ========= */

                $brand = null;

                if ($brandName) {
                    $brand = Brand::whereRaw('LOWER(name) = ?', [
                        strtolower($brandName)
                    ])->first();

                    if (!$brand) {
                        $brand = Brand::create([
                            'name' => $brandName,
                            'slug' => Str::slug($brandName),
                            'category_id' => $category?->id,
                            'is_active' => 1
                        ]);
                    }
                }

                /* ========= UPSERT ========= */

                $baseSlug = Str::slug($name);
                $slug = $baseSlug;
                $count = 1;

                // 👉 FIX TRÙNG SLUG
                while (Product::where('slug', $slug)->exists()) {
                    $slug = $baseSlug . '-' . $count++;

                }


                $exists = Product::where('sku', $sku)->exists();

                if ($exists && !$allowDuplicate) {

                    $errors[] = [
                        'row' => $rowNumber,
                        'name' => $name,
                        'sku' => $sku,
                        'barcode' => $barcode,
                        'category' => $categoryName,
                        'brand' => $brandName,
                        'sell_price' => $sellPrice,
                        'type' => $type,
                        'active' => $active,
                        'image_name' => $rawImageName,
                        'error' => 'Trùng SKU'
                    ];

                    continue;
                }

                // 👉 FORCE UNIQUE nếu cho phép
                if ($exists && $allowDuplicate) {
                    $sku = $sku . '-' . time();
                }

               

                $imagePath = null;

                if ($imageName) {

                    $found = false;

                    foreach ($imageFiles as $key => $file) {

                        if (strpos($key, $imageName) !== false) {
                            $ext = $file->getClientOriginalExtension();

                            $imagePath = $file->storeAs(
                                'products',
                                $sku . '_' . time() . '.' . $ext,
                                'public'
                            );

                            $found = true;
                            break;
                        }
                    }

                    if (!$found) {
                        $errors[] = [
                            'row' => $rowNumber,
                            'name' => $name,
                            'sku' => $sku,
                            'barcode' => $barcode,
                            'category' => $categoryName,
                            'brand' => $brandName,
                            'sell_price' => $sellPrice,
                            'type' => $type,
                            'active' => $active,
                            'image_name' => $rawImageName, // 🔥 FIX QUAN TRỌNG
                            'error' => 'Không tìm thấy ảnh'
                        ];
                        continue;
                    }
                }

                Product::create([
                    'name'        => $name,
                    'sku'         => $sku,
                    'slug'        => $slug,
                    'barcode'     => $barcode ?: null,
                    'category_id' => $category?->id,
                    'brand_id'    => $brand?->id,

                    'sell_price'  => $sellPrice,

                    'cost_price'  => 0,
                    'stock'       => 0,

                    'product_type'=> $type,
                    'is_active'   => $active,

                    'search_text' => $name . ' ' . $sku,
                    'image' => $imagePath,
                ]);

                $success++;
            }


            DB::commit();

            return [
                'success' => true,
                'count' => $success,
                'errors' => $errors,
                'error_count' => count($errors)
            ];

        } catch (\Throwable $e) {

            DB::rollBack();

            return [
                'success' => false,
                'errors' => [$e->getMessage()]
            ];
        }
    }
}