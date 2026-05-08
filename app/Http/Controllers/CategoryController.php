<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Services\Category\CategoryService;
use App\Repositories\Category\CategoryRepository;
use App\Http\Requests\Category\StoreCategoryRequest;

class CategoryController extends Controller
{
    public function __construct(
        protected CategoryRepository $repository,
        protected CategoryService $service,
    ) {
    }

    public function index()
    {
        $categories = $this->repository->paginate();

        return Inertia::render('Categories/Index', [
            'categories' => $categories
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
}