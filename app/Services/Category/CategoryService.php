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

    public function update($category, array $data)
    {
        $data['slug'] = Str::slug($data['name']);

        return $this->repository->update(
            $category,
            $data
        );
    }
    // Soft delete
    public function delete($category)
    {
        return $this->repository->delete($category);
    }

    public function restore($id)
    {
        return $this->repository->restore($id);
    }
}