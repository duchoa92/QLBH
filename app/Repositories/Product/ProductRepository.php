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

        $booleanSearch = null;

        if ($search) {
            $terms = explode(' ', $search);

            $booleanSearch = collect($terms)
                ->filter()
                ->map(fn($t) => '+' . $t . '*')
                ->implode(' ');
        }

        return $this->model
            ->query()
            ->with([
                'category:id,name',
                'brand:id,name',
                'imeis:id,product_id,imei'
            ])

            ->when($search, function ($query) use ($search, $booleanSearch) {
                $query->where(function ($q) use ($search, $booleanSearch) {

                    // FULLTEXT
                    if ($booleanSearch) {
                        $q->whereRaw(
                            "MATCH(search_text) AGAINST(? IN BOOLEAN MODE)",
                            [$booleanSearch]
                        );
                    }

                    // barcode (fallback)
                    $q->orWhere('barcode', 'like', "%$search%");

                    // category
                    $q->orWhereHas('category', function ($catQuery) use ($search) {
                        $catQuery->where('name', 'like', "%$search%");
                    });

                    // brand
                    $q->orWhereHas('brand', function ($brandQuery) use ($search) {
                        $brandQuery->where('name', 'like', "%$search%");
                    });

                    // IMEI (quan trọng)
                    $q->orWhereHas('imeis', function ($iq) use ($search) {
                        $iq->where('imei', 'like', "%$search%");
                    });

                });
            })

            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

}