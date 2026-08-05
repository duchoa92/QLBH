<?php

namespace App\Services\Product;

use DB;
use App\Repositories\Product\ProductRepository;
use App\Services\Base\BaseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use App\Models\Product;
use App\Models\ProductImei;
use App\Models\ProductVariant;

class ProductService extends BaseService
{
    public function __construct(
        ProductRepository $repository
    ) {
        $this->repository = $repository;
    }

    // Tạo mới sản phẩm
    public function create(array $data): Model
    {
        return DB::transaction(function () use ($data) {

            $data['slug'] = Str::slug($data['name']);

            unset($data['sku']); // LUÔN bỏ SKU frontend

            //  
            $data['manage_stock_by_serial'] = $data['manage_stock_by_serial'] ?? false;

            // đảm bảo barcode luôn tồn tại
            $data['barcode'] = $data['barcode'] ?? null;

            // upload ảnh
            if (isset($data['image'])) {
                $data['image'] = $data['image']->store('products', 'public');
            }

            // tạo SKU trước khi insert
            $tempProduct = new Product($data);

            $data['sku'] = $this->generateSku($tempProduct);

            $product = $this->repository->create($data);

            $product->sku = $this->generateSku($product);
            $product->save();

            // nếu sản phẩm KHÔNG phải IMEI → xóa IMEI gửi lên (tránh bug)
            if (!$product->manage_stock_by_serial) {
                unset($data['imeis']);
            }

            // ==========================
            // VARIANTS + IMEI (CHUẨN POS)
            // ==========================
            if (!empty($data['variants'])) {

                foreach ($data['variants'] as $v) {

                    $variant = ProductVariant::create([
                        'product_id' => $product->id,

                        'sku' => empty($v['sku'])
                            ? $this->generateSku($product, $v)
                            : $v['sku'],

                        'barcode' => $v['barcode'] ?? null,

                        'color' => $v['color'] ?? null,
                        'storage' => $v['storage'] ?? null,
                        'version' => $v['version'] ?? null,

                        'cost_price' => $v['cost_price'] ?? 0,
                        'sell_price' => $v['sell_price'] ?? 0,
                        'stock' => $v['stock'] ?? 0,
                    ]);

                    //  IMEI theo từng VARIANT
                    if ($product->manage_stock_by_serial && !empty($v['imeis'])) {

                        $imeis = preg_split('/\r\n|\r|\n/', trim($v['imeis']));

                        foreach ($imeis as $imei) {

                            $imei = trim($imei);
                            if (!$imei) continue;

                            // tránh trùng
                            $exists = ProductImei::where('imei', $imei)->exists();
                            if ($exists) continue;

                            ProductImei::create([
                                'product_id' => $product->id,
                                'variant_id' => $variant->id, // 🔥 CHÌA KHÓA
                                'imei' => $imei,
                            ]);
                        }
                    }
                }
            }
            


            // XỬ LÝ IMEI CHUẨN HƠN (KHÔNG PHÁ CODE CŨ)
            if ($product->manage_stock_by_serial && !empty($data['imeis'])) {

                $imeis = preg_split('/\r\n|\r|\n/', trim($data['imeis']));

                foreach ($imeis as $imei) {

                    $imei = trim($imei);

                    // bỏ rỗng
                    if (!$imei) continue;

                    // tránh trùng IMEI trong DB
                    $exists = ProductImei::where('imei', $imei)->exists();
                    if ($exists) continue;

                    ProductImei::create([
                        'product_id' => $product->id,
                        'imei' => $imei,
                    ]);
                }
            }

            return $product;
        });
    }

    // Cập nhập sản phẩm
    public function update(Model $model, array $data): Model {

        $data['slug'] = Str::slug($data['name']);

        $data['manage_stock_by_serial'] = $data['manage_stock_by_serial'] ?? false;


        if (isset($data['image'])) {
            // xóa ảnh cũ nếu có
            if ($model->image) {
                Storage::disk('public')
                    ->delete($model->image);
            }

            $data['image'] = $data['image']
                ->store(
                    'products',
                    'public'
                );
        }

        return $this->repository->update(
            $model,
            $data
        );
    }

    // Hiển thị sản phẩm đã xóa
    public function trash($perPage = 10)
    {
        return $this->repository
            ->trash($perPage);
    }

    // Khôi phục sản phẩm
    public function restore(int $id): bool
    {
        return $this->repository
            ->restore($id);
    }

    // Tự tạo SKU dựa trên SP
    private function makeCodeFromWords($text)
    {
        $text = Str::slug($text);
        $words = explode('-', $text);

        if (count($words) == 1) {
            return strtoupper(substr($words[0], 0, 3));
        }

        if (count($words) == 2) {
            return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 2));
        }

        // >= 3 từ
        return strtoupper(implode('', array_map(fn($w) => substr($w, 0, 1), $words)));
    }

    private function short($text, $len = 3)
    {
        return strtoupper(substr(Str::slug($text), 0, $len));
    }

    private function generateSku($product, $variant = null)
    {
        $category = \App\Models\Category::find($product->category_id)?->name ?? 'GEN';
        $brand = \App\Models\Brand::find($product->brand_id)?->name ?? 'GEN';

        $catCode = $this->makeCodeFromWords($category);
        $brandCode = $this->makeCodeFromWords($brand);

        // SKU PRODUCT
        if (!$variant) {
            return "{$catCode}{$brandCode}";
        }

        $parts = [];

        if (!empty($variant['storage'])) {
            $parts[] = strtoupper($variant['storage']); // 128G
        }

        if (!empty($variant['color'])) {
            $parts[] = $this->short($variant['color']); // RED
        }

        if (!empty($variant['version'])) {
            $parts[] = $this->short($variant['version']);
        }

        $suffix = implode('-', $parts);

        return "{$catCode}{$brandCode}" . ($suffix ? "-{$suffix}" : '');
    }
    
}