<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductImportService
{

    private function pushError(
        &$errors,
        $rowNumber,
        $name,
        $sku,
        $barcode,
        $categoryName,
        $brandName,
        $sellPrice,
        $costPrice,
        $stock,
        $type,
        $active,
        $rawImageName,
        $message
    ) {
        $errors[] = [
            'row'        => $rowNumber,
            'name'       => $name ?? '',
            'sku'        => $sku ?? '',
            'barcode'    => $barcode ?? '',
            'category'   => $categoryName ?? '',
            'brand'      => $brandName ?? '',
            'sell_price' => $sellPrice ?? '',
            'cost_price' => $costPrice ?? '',
            'stock'      => $stock ?? '',
            'type'       => $type ?? '',
            'active'     => $active ?? '',
            'image_name' => $rawImageName ?? '',
            'error'      => $message,
        ];
    }

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
                $sellPrice    = trim($row[6] ?? '');
                $costPrice    = trim($row[7] ?? '');
                $stock        = trim($row[8] ?? '');
                $type         = $row[9] ?? 'normal';
                $active       = $row[10] ?? 1;
                $rawImageName = trim($row[11] ?? '');
                                
                


                $rowNumber = $index + 1;



                /* ========= VALIDATE ========= */

                if (!$name) {
                    $this->pushError(
                        $errors,
                        $rowNumber,
                        $name,
                        $sku,
                        $barcode,
                        $categoryName,
                        $brandName,
                        $sellPrice,
                        $costPrice,
                        $stock,
                        $type,
                        $active,
                        $rawImageName,
                        'Thiếu tên sản phẩm'
                    );
                    continue;
                }

                if (!$sku) {
                    $this->pushError(
                        $errors,
                        $rowNumber,
                        $name,
                        $sku,
                        $barcode,
                        $categoryName,
                        $brandName,
                        $sellPrice,
                        $costPrice,
                        $stock,
                        $type,
                        $active,
                        $rawImageName,
                        'Thiếu SKU'
                    );
                    continue;
                }

                if ($sellPrice === '' || !is_numeric($sellPrice)) {
                    $this->pushError(  
                        $errors,
                        $rowNumber,
                        $name,
                        $sku,
                        $barcode,
                        $categoryName,
                        $brandName,
                        $sellPrice,
                        $costPrice,
                        $stock,
                        $type,
                        $active,
                        $rawImageName,
                        'Thiếu giá bán'
                    );
                    continue;
                }

                // cost_price KHÔNG bắt buộc
                if ($costPrice !== '' && !is_numeric($costPrice)) {
                    $this->pushError(
                        
                        $errors,
                        $rowNumber,
                        $name,
                        $sku,
                        $barcode,
                        $categoryName,
                        $brandName,
                        $sellPrice,
                        $costPrice,
                        $stock,
                        $type,
                        $active,
                        $rawImageName,
                        'Giá nhập không hợp lệ'
                    );
                    continue;
                }

                // stock KHÔNG bắt buộc
                if ($stock !== '' && !is_numeric($stock)) {
                    $this->pushError(
                        
                        $errors,
                        $rowNumber,
                        $name,
                        $sku,
                        $barcode,
                        $categoryName,
                        $brandName,
                        $sellPrice,
                        $costPrice,
                        $stock,
                        $type,
                        $active,
                        $rawImageName,
                        'Tồn kho không hợp lệ'
                    );
                    continue;
                }

                /* ========= CHECK TRÙNG SKU TRONG FILE ========= */

                if (in_array($sku, $skuList)) {
                    $this->pushError(
                        $errors,
                        $rowNumber,
                        $name,
                        $sku,
                        $barcode,
                        $categoryName,
                        $brandName,
                        $sellPrice,
                        $costPrice,
                        $stock,
                        $type,
                        $active,
                        $rawImageName,
                        'SKU trong file bị lặp'
                    );
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

                    $this->pushError(
                        
                        $errors,
                        $rowNumber,
                        $name,
                        $sku,
                        $barcode,
                        $categoryName,
                        $brandName,
                        $sellPrice,
                        $costPrice,
                        $stock,
                        $type,
                        $active,
                        $rawImageName,
                        'SKU đã tồn tại'
                    );

                    continue;
                }

                // 👉 FORCE UNIQUE nếu cho phép
                if ($exists && $allowDuplicate) {
                    $sku = $sku . '-' . time();
                }
                
                $imagePath = null;

                if (!empty($rawImageName)) {

                    $imageKey = strtolower(
                        preg_replace('/[^a-z0-9]/', '', pathinfo($rawImageName, PATHINFO_FILENAME))
                    );

                    if (isset($imageFiles[$imageKey])) {

                        $file = $imageFiles[$imageKey];

                        $ext = $file->getClientOriginalExtension();

                        $imagePath = $file->storeAs(
                            'products',
                            $sku . '_' . time() . '.' . $ext,
                            'public'
                        );

                    } else {

                        $this->pushError(
                            $errors,
                            $rowNumber,
                            $name,
                            $sku,
                            $barcode,
                            $categoryName,
                            $brandName,
                            $sellPrice,
                            $costPrice,
                            $stock,
                            $type,
                            $active,
                            $rawImageName,
                            'Không tìm thấy file ảnh upload'
                        );

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

                    'cost_price'  => $costPrice !== '' ? $costPrice : 0,
                    'stock'       => $stock !== '' ? $stock : 0,

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