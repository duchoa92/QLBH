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
        $categoryId = request('category_id');
        $brandId = request('brand_id');
        $stock = request('stock'); // in_stock | out_stock
        $status = request('status'); // active | inactive

        return $this->model
            ->query()
            ->with([
                'category:id,name',
                'brand:id,name',
                'imeis:id,product_id,imei'
            ])

            /*
            |--------------------------------------------------------------------------
            | FILTER (CHẠY TRƯỚC SEARCH)
            |--------------------------------------------------------------------------
            */

            ->when($categoryId, fn($q) =>
                $q->where('category_id', $categoryId)
            )

            ->when($brandId, fn($q) =>
                $q->where('brand_id', $brandId)
            )

            ->when($stock === 'in_stock', fn($q) =>
                $q->where('stock', '>', 0)
            )

            ->when($stock === 'out_stock', fn($q) =>
                $q->where('stock', '=', 0)
            )

            ->when($status === 'active', fn($q) =>
                $q->where('is_active', true)
            )

            ->when($status === 'inactive', fn($q) =>
                $q->where('is_active', false)
            )

            /*
            |--------------------------------------------------------------------------
            | SEARCH (GIỮ NGUYÊN LOGIC CỦA BẠN)
            |--------------------------------------------------------------------------
            */

            ->when($search, function ($query) use ($search) {

                $search = trim($search);

                // 👉 IMEI
                if (preg_match('/^\d{6,}$/', $search)) {
                    $query->whereHas('imeis', function ($q) use ($search) {
                        $q->where('imei', 'like', "%$search%");
                    });
                    return;
                }

                // 👉 FULLTEXT
                $terms = explode(' ', $search);

                $booleanSearch = collect($terms)
                    ->filter()
                    ->map(fn($t) => '+' . $t . '*')
                    ->implode(' ');

                $query->where(function ($q) use ($search, $booleanSearch) {

                    if ($booleanSearch) {
                        $q->whereRaw(
                            "MATCH(search_text) AGAINST(? IN BOOLEAN MODE)",
                            [$booleanSearch]
                        );
                    }

                    // fallback cho từ ngắn
                    $q->orWhere('search_text', 'like', "%$search%");

                    $q->orWhere('barcode', 'like', "%$search%");

                    $q->orWhereHas('category', function ($catQuery) use ($search) {
                        $catQuery->where('name', 'like', "%$search%");
                    });

                    $q->orWhereHas('brand', function ($brandQuery) use ($search) {
                        $brandQuery->where('name', 'like', "%$search%");
                    });

                });

            })

            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

}