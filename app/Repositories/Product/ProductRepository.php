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

   

    public function paginate(int $perPage = 10): LengthAwarePaginator
    {
        $search = request('search');

        return $this->model
            ->query()
            ->with([
                'category:id,name',
                'brand:id,name',
                'imeis:id,product_id,imei'
            ])

            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {

                    $q->where('name', 'like', "%$search%")
                    ->orWhere('sku', 'like', "%$search%")
                    ->orWhere('barcode', 'like', "%$search%")

                    ->orWhereHas('category', function ($catQuery) use ($search) {
                        $catQuery->where('name', 'like', "%$search%");
                    })

                    ->orWhereHas('brand', function ($brandQuery) use ($search) {
                        $brandQuery->where('name', 'like', "%$search%");
                    })

                    ->orWhereHas('imeis', function ($iq) use ($search) {
                        $iq->where('imei', 'like', "%$search%");
                    });
                });
            })

            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

}