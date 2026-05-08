<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use App\Services\Category\CategoryService;
use App\Repositories\Category\CategoryRepository;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Models\Category;
use App\Http\Requests\Category\UpdateCategoryRequest;

class CategoryController extends Controller implements HasMiddleware
{
    public function __construct(
        protected CategoryRepository $repository,
        protected CategoryService $service,
    ) {
    }

    public static function middleware(): array
    {
        return [

            new Middleware(
                'permission:categories.view',
                only: ['index']
            ),

            new Middleware(
                'permission:categories.create',
                only: ['create', 'store']
            ),

            new Middleware(
                'permission:categories.edit',
                only: ['edit', 'update']
            ),

            new Middleware(
                'permission:categories.delete',
                only: ['destroy', 'restore']
            ),

        ];
    }

    public function index()
    {
        $categories = $this->repository->paginate();

        return Inertia::render('Categories/Index', [
            'categories' => $categories,

            'filters' => [
                'search' => request('search')
            ]
        ]);
    }

    public function create()
    {
        return Inertia::render('Categories/Create');
    }

    public function store(StoreCategoryRequest $request)
    {
        $this->service->create(
            $request->validated()
        );

        return redirect()
            ->route('categories.index')
            ->with('success', 'Tạo danh mục thành công');
    }

    public function edit(Category $category)
    {
        return Inertia::render('Categories/Edit', [
            'category' => $category
        ]);
    }

    public function update(UpdateCategoryRequest $request, Category $category) 
    {

        $this->service->update(
            $category,
            $request->validated()
        );

        return redirect()
            ->route('categories.index')
            ->with(
                'success',
                'Cập nhật thành công'
            );
    }

    public function destroy(Category $category)
    {
        $this->service->delete($category);

        return redirect()
            ->back()
            ->with(
                'success',
                'Xóa thành công'
            );
    }

    public function restore($id)
    {
        $this->service->restore($id);

        return redirect()
            ->back()
            ->with(
                'success',
                'Khôi phục thành công'
            );
    }
}