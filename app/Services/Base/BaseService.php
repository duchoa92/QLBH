<?php

namespace App\Services\Base;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Repositories\Base\BaseRepository;

class BaseService
{
    protected BaseRepository $repository;

    public function __construct(
        BaseRepository $repository
    ) {
        $this->repository = $repository;
    }

    // Phân trang
    public function paginate(
        int $perPage = 10
    ): LengthAwarePaginator {

        return $this->repository
            ->paginate($perPage);
    }

    // Tạo mới sản phẩm
    public function create(array $data): Model
    {
        return $this->repository
            ->create($data);
    }

    // Tìm sản phẩm theo ID
    public function findById(int $id): Model
    {
        return $this->repository
            ->findById($id);
    }

    // Cập nhập sản phẩm
    public function update(
        Model $model,
        array $data
    ): Model {

        return $this->repository
            ->update($model, $data);
    }

    // Xóa sản phẩm
    public function delete(Model $model): bool
    {
        return $this->repository
            ->delete($model);
    }

    // Khôi phục sản phẩm
    public function restore(int $id): bool
    {
        return $this->repository
            ->restore($id);
    }
}