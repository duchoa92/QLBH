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

    // Thùng rác
    public function trash($perPage = 10)
    {
        return $this->model
            ->onlyTrashed()
            ->with([
                'category:id,name',
                'brand:id,name'
            ])
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }
   
    
    public function paginate(int $perPage = 10): LengthAwarePaginator
    {
        $perPage = request('per_page', $perPage);
        $search = request('search');
        $categoryId = request('category_id');
        $brandId = request('brand_id');
        $stock = request('stock');
        $status = request('status');

        return $this->model
            ->query()
            ->with([
                'category:id,name',
                'brand:id,name',
                'imeis:id,product_id,imei'
            ])

            /*
            |------------------------------------------------------------------
            | FILTER
            |------------------------------------------------------------------
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
                $q->where('stock', 0)
            )

            ->when($status === 'active', fn($q) =>
                $q->where('is_active', true)
            )

            ->when($status === 'inactive', fn($q) =>
                $q->where('is_active', false)
            )

            /*
            |------------------------------------------------------------------
            | SEARCH
            |------------------------------------------------------------------
            */

            ->when($search, function ($query) use ($search) {

                $search = trim($search);

                $query->where(function ($q) use ($search) {

                    if (preg_match('/^\d{6,}$/', $search)) {
                        $q->orWhereHas('imeis', function ($imeiQuery) use ($search) {
                            $imeiQuery->where('imei', 'like', "%$search%");
                        });
                    }

                    $terms = explode(' ', $search);

                    $booleanSearch = collect($terms)
                        ->filter()
                        ->map(fn($t) => '+' . $t . '*')
                        ->implode(' ');

                    if ($booleanSearch) {
                        $q->orWhereRaw(
                            "MATCH(search_text) AGAINST(? IN BOOLEAN MODE)",
                            [$booleanSearch]
                        );
                    }

                    $q->orWhere('search_text', 'like', "%$search%");
                    $q->orWhere('barcode', 'like', "%$search%");

                    $q->orWhereHas('category', fn($q2) =>
                        $q2->where('name', 'like', "%$search%")
                    );

                    $q->orWhereHas('brand', fn($q2) =>
                        $q2->where('name', 'like', "%$search%")
                    );

                });
            })

            /*
            |------------------------------------------------------------------
            | SORT
            |------------------------------------------------------------------
            */

            ->when(request('sort_by'), function ($q) {

                $allowed = ['id', 'name', 'sell_price', 'stock'];

                $sortBy = request('sort_by');

                if (!in_array($sortBy, $allowed)) return;

                $q->orderBy(
                    $sortBy,
                    request('sort_order', 'asc')
                );

            }, function ($q) {
                $q->latest();
            })

            /*
            |------------------------------------------------------------------
            | PAGINATION
            |------------------------------------------------------------------
            */

            ->paginate($perPage)
            ->withQueryString();
    }

}