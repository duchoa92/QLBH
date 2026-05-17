<?php

namespace App\Services\Brand;

use App\Repositories\Brand\BrandRepository;
use App\Services\Base\BaseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BrandService extends BaseService
{
    public function __construct(
        BrandRepository $repository
    ) {
        $this->repository = $repository;
    }

   // Tạo mới thương hiệu
    public function create(
        array $data
    ): Model {

        $data['slug'] = Str::slug(
            $data['name']
        );

        return $this->repository
            ->create($data);
    }

    // Cập nhật thương hiệu
    public function update(
        Model $model,
        array $data
    ): Model {

        $data['slug'] = Str::slug(
            $data['name']
        );

        return $this->repository
            ->update(
                $model,
                $data
            );
    }

    // Hiển thị thương hiệu đã xóa
    public function trash(
        int $perPage = 10
    ) {
        return $this->repository
            ->trash($perPage);
    }
}