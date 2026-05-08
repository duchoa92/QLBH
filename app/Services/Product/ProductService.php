<?php

namespace App\Services\Product;

use App\Repositories\Product\ProductRepository;

use App\Services\Base\BaseService;

class ProductService extends BaseService
{
    public function __construct(ProductRepository $repository)
    {
        $this->repository = $repository;
    }
}