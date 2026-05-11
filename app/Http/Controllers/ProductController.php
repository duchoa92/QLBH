<?php

namespace App\Http\Controllers;

use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Services\Product\ProductService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function __construct(
        protected ProductService $service
    ) {}

    public function index(): Response
    {
        return Inertia::render(
            'Products/Index',
            [
                'products' => $this->service->paginate(),
            ]
        );
    }

    // Show the form for creating a new product
    public function create(): Response
    {
        return Inertia::render(
            'Products/Create',
            [
                'categories' => Category::query()
                    ->select('id', 'name')
                    ->get(),
            ]
        );
    }

    // Show the form for editing a product
    public function edit(
        Product $product
    ): Response {

        return Inertia::render(
            'Products/Edit',
            [
                'product' => $product,

                'categories' => Category::query()
                    ->select('id', 'name')
                    ->get(),
            ]
        );
    }

    // Store a new product
    public function store(
        StoreProductRequest $request
    ): RedirectResponse {

        $this->service->create(
            $request->validated()
        );

        return redirect()
            ->route('products.index')
            ->with(
                'success',
                'Thêm sản phẩm thành công'
            );
    }

    // Cập nhập sản phẩm
    public function update(
        UpdateProductRequest $request,
        Product $product
    ): RedirectResponse {

        $this->service->update(
            $product,
            $request->validated()
        );

        return redirect()
            ->route('products.index')
            ->with(
                'success',
                'Cập nhật sản phẩm thành công'
            );
    }



   //
    public function destroy(
        Product $product
    ): RedirectResponse {

        $this->service->delete($product);

        return redirect()
            ->back()
            ->with(
                'success',
                'Xóa thành công'
            );
    }

    // Hiển thị sản phẩm đã xóa
    public function trash(): Response
    {
        return Inertia::render(
            'Products/Trash',
            [
                'products' => Product::query()
                    ->onlyTrashed()
                    ->latest()
                    ->paginate(10)
                    ->withQueryString(),
            ]
        );
    }

    // Khôi phục sản phẩm
    public function restore(
        int $id
    ): RedirectResponse {

        $this->service->restore($id);

        return redirect()
            ->back()
            ->with(
                'success',
                'Khôi phục thành công'
            );
    }
}