<?php

namespace App\Services\Product;

use DB;
use App\Repositories\Product\ProductRepository;
use App\Services\Base\BaseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
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

            // ✅ đảm bảo barcode luôn tồn tại
            $data['barcode'] = $data['barcode'] ?? null;

            // upload ảnh
            if (isset($data['image'])) {
                $data['image'] = $data['image']->store('products', 'public');
            }

            $product = $this->repository->create($data);

            // ==========================
            // VARIANTS + IMEI (CHUẨN POS)
            // ==========================
            if (!empty($data['variants'])) {

                foreach ($data['variants'] as $v) {

                    $variant = ProductVariant::create([
                        'product_id' => $product->id,

                        'sku' => $v['sku'] ?? null,
                        'barcode' => $v['barcode'] ?? null,

                        'color' => $v['color'] ?? null,
                        'storage' => $v['storage'] ?? null,
                        'version' => $v['version'] ?? null,

                        'cost_price' => $v['cost_price'] ?? 0,
                        'sell_price' => $v['sell_price'] ?? 0,
                        'stock' => $v['stock'] ?? 0,
                    ]);

                    // 🔥 IMEI theo từng VARIANT
                    if (!empty($v['imeis'])) {

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


            // ✅ XỬ LÝ IMEI CHUẨN HƠN (KHÔNG PHÁ CODE CŨ)
            if (!empty($data['imeis'])) {

                $imeis = preg_split('/\r\n|\r|\n/', trim($data['imeis']));

                foreach ($imeis as $imei) {

                    $imei = trim($imei);

                    // bỏ rỗng
                    if (!$imei) continue;

                    // ❗ tránh trùng IMEI trong DB
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
    public function update(
        Model $model,
        array $data
    ): Model {

        $data['slug'] = Str::slug(
            $data['name']
        );


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
    
}