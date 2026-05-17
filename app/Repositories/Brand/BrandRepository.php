<?php

namespace App\Repositories\Brand;

use App\Models\Brand;
use App\Repositories\Base\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BrandRepository extends BaseRepository
{
    public function __construct(
        Brand $model
    ) {
        $this->model = $model;
    }
// Phân trang
    public function paginate(
        int $perPage = 10
    ): LengthAwarePaginator {

        return $this->model
            ->query()

            ->when(
                request('search'),
                function ($query) {

                    $query->where(
                        'name',
                        'like',
                        '%' . request('search') . '%'
                    );
                }
            )

            ->latest()

            ->paginate($perPage)

            ->withQueryString();
    }

    // Hiển thị thương hiệu đã xóa
     public function trash(
        int $perPage = 10
    ): LengthAwarePaginator {

        return $this->model
            ->onlyTrashed()

            ->latest()

            ->paginate($perPage);
    }
}