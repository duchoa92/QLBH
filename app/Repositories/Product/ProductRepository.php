<?php

namespace App\Repositories\Product;

use App\Models\Product;
use App\Repositories\Base\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProductRepository extends BaseRepository
{
    public function __construct(Product $model)
    {
        $this->model = $model;
    }

    // Hiển thị sản phẩm đã xóa
    public function trash($perPage = 10)
    {
        return $this->model
            ->onlyTrashed()

            ->latest()

            ->paginate($perPage)

            ->withQueryString();
    }

    // Phân trang
    public function paginate(
        int $perPage = 10
    ): LengthAwarePaginator {

        return $this->model
            ->query()

            ->with([
                'category:id,name',
            ])

            ->withTrashed()

            ->when(
                request('search'),
                function ($query) {

                    $query->where(
                        'name',
                        'like',
                        '%' . request('search') . '%'
                    )

                    ->orWhere(
                        'code',
                        'like',
                        '%' . request('search') . '%'
                    )

                    ->orWhere(
                        'barcode',
                        'like',
                        '%' . request('search') . '%'
                    );
                }
            )

            ->latest()

            ->paginate($perPage)

            ->withQueryString();
    }
}