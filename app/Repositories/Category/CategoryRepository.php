<?php

namespace App\Repositories\Category;

use App\Models\Category;

use App\Repositories\Base\BaseRepository;

class CategoryRepository extends BaseRepository
{
    public function __construct(Category $model)
    {
        $this->model = $model;
    }
}