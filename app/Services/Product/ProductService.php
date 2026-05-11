<?php

namespace App\Services\Product;

use App\Repositories\Product\ProductRepository;
use App\Services\Base\BaseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ProductService extends BaseService
{
    public function __construct(
        ProductRepository $repository
    ) {
        $this->repository = $repository;
    }

    // Tạo mới sản phẩm
    public function create(
        array $data
    ): Model {

        $data['slug'] = Str::slug(
            $data['name']
        );

        return $this->repository->create($data);
    }

    // Cập nhập sản phẩm
    public function update(
        Model $model,
        array $data
    ): Model {

        $data['slug'] = Str::slug(
            $data['name']
        );

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