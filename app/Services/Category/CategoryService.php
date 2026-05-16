<?php

namespace App\Services\Category;

use App\Models\Category;
use App\Repositories\Category\CategoryRepository;
use App\Services\Base\BaseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CategoryService extends BaseService
{
    public function __construct(
        CategoryRepository $repository
    ) {
        $this->repository = $repository;
    }

    public function create(array $data): Model
    {
        $data['slug'] = Str::slug($data['name']);

        return $this->repository->create($data);
    }

    public function update(
        Model $model,
        array $data
    ): Model {

        $data['slug'] = Str::slug($data['name']);

        return $this->repository->update(
            $model,
            $data
        );
    }
}