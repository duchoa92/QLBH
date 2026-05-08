<?php

namespace App\Repositories\Category;

use App\Models\Category;

class CategoryRepository
{
    public function paginate($perPage = 10)
    {
        return Category::query()
            ->latest()
            ->paginate($perPage);
    }

    public function create(array $data): Category
    {
        return Category::create($data);
    }
}