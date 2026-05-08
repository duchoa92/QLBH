<?php

namespace App\Repositories\Category;

use App\Models\Category;

class CategoryRepository
{
    public function paginate($perPage = 10)
    {
        return Category::query()

            ->when(
                request('search'),
                function ($query) {

                    $query->where(
                        'name',
                        'like',
                        '%' . request('search') . '%'
                    );
                }
            )

            ->latest()

            ->paginate($perPage)

            ->withQueryString();
    }
    // Tạo
    public function create(array $data): Category
    {
            return Category::create($data);
        }

        public function findById($id): Category
    {
        return Category::findOrFail($id);
    }

    // Cập nhật
    public function update(Category $category, array $data)
    {
        $category->update($data);

        return $category;
    }

    // Xóa Mềm
    public function delete(Category $category)
    {
        return $category->delete();
    }

    public function restore($id)
    {
        $category = Category::withTrashed()
            ->findOrFail($id);

        return $category->restore();
    }

    // Hiện dữ liệu đã xóa
    
    
}