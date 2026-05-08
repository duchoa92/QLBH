<?php

namespace App\Repositories\Product;

use App\Models\Product;

use App\Repositories\Base\BaseRepository;

class ProductRepository extends BaseRepository
{
    public function __construct(Product $model)
    {
        $this->model = $model;
    }
}