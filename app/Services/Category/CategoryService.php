<?php

namespace App\Services\Category;

use App\Repositories\Category\CategoryRepository;

use App\Services\Base\BaseService;

class CategoryService extends BaseService
{
    public function __construct(CategoryRepository $repository)
    {
        $this->repository = $repository;
    }
}