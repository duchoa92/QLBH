<?php

namespace App\Services\Category;

use App\Repositories\Category\CategoryRepository;
use Illuminate\Support\Str;

class CategoryService
{
    public function __construct(
        protected CategoryRepository $repository
    ) {
    }

    public function create(array $data)
    {
        $data['slug'] = Str::slug($data['name']);

        return $this->repository->create($data);
    }
}