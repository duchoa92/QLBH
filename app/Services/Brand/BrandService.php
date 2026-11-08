<?php

namespace App\Services\Brand;

use App\Repositories\Brand\BrandRepository;
use App\Models\Brand;
use App\Services\Base\BaseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BrandService extends BaseService
{
    public function __construct(
        BrandRepository $repository
    ) {
        $this->repository = $repository;
    }

   // Tạo mới thương hiệu
    public function create(
        array $data
    ): Model {

        $categoryIds = $data['category_ids'] ?? [];
        unset($data['category_ids']);

        $data['slug'] = $this->uniqueSlug($data['name']);

        return DB::transaction(function () use ($data, $categoryIds) {
            $brand = $this->repository->create($data);
            $brand->categories()->sync($categoryIds);

            return $brand;
        });
    }

    // Cập nhật thương hiệu
    public function update(
        Model $model,
        array $data
    ): Model {

        $categoryIds = $data['category_ids'] ?? null;
        unset($data['category_ids']);

        $data['slug'] = $this->uniqueSlug($data['name'], $model->getKey());

        return DB::transaction(function () use ($model, $data, $categoryIds) {
            $brand = $this->repository->update($model, $data);

            if ($categoryIds !== null) {
                $brand->categories()->sync($categoryIds);
            }

            return $brand;
        });
    }

    // Hiển thị thương hiệu đã xóa
    public function trash(
        int $perPage = 10
    ) {
        return $this->repository
            ->trash($perPage);
    }

    private function uniqueSlug(string $name, int|string|null $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'brand';
        $slug = $base;
        $suffix = 1;

        while (true) {
            $query = Brand::withTrashed()->where('slug', $slug);

            if ($ignoreId !== null) {
                $query->whereKeyNot($ignoreId);
            }

            if (!$query->exists()) {
                return $slug;
            }

            $slug = $base . '-' . ++$suffix;
        }
    }
}
