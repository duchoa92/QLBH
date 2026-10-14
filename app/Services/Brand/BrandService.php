<?php

namespace App\Services\Brand;

use App\Repositories\Brand\BrandRepository;
use App\Models\Brand;
use App\Services\Base\BaseService;
use Illuminate\Database\Eloquent\Model;
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

        $data['slug'] = $this->uniqueSlug($data['name'], $data['category_id']);

        return $this->repository
            ->create($data);
    }

    // Cập nhật thương hiệu
    public function update(
        Model $model,
        array $data
    ): Model {

        $data['slug'] = $this->uniqueSlug($data['name'], $data['category_id'], $model->getKey());

        return $this->repository
            ->update(
                $model,
                $data
            );
    }

    // Hiển thị thương hiệu đã xóa
    public function trash(
        int $perPage = 10
    ) {
        return $this->repository
            ->trash($perPage);
    }

    private function uniqueSlug(string $name, int|string $categoryId, int|string|null $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'brand';
        $slug = $base;
        $suffix = 0;

        do {
            $query = Brand::withTrashed()->where('slug', $slug);

            if ($ignoreId !== null) {
                $query->whereKeyNot($ignoreId);
            }

            if (!$query->exists()) {
                return $slug;
            }

            $suffix++;
            $slug = $base . '-' . $categoryId . ($suffix > 1 ? '-' . $suffix : '');
        } while (true);
    }
}
