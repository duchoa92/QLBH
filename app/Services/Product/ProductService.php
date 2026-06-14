<?php

namespace App\Services\Product;

use DB;
use App\Repositories\Product\ProductRepository;
use App\Services\Base\BaseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use App\Models\ProductImei;

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

            if (isset($data['image'])) {
                $data['image'] = $data['image']->store('products', 'public');
            }

            $product = $this->repository->create($data);

            if (!empty($data['imeis'])) {
                $imeis = preg_split('/\r\n|\r|\n/', trim($data['imeis']));

                foreach ($imeis as $imei) {
                    if (empty($imei)) continue;

                    ProductImei::create([
                        'product_id' => $product->id,
                        'imei' => trim($imei),
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