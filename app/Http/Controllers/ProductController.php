<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

use App\Models\Brand;
use App\Models\Product;
use App\Models\Category;
use App\Models\Unit;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

use Inertia\Response;

use App\Services\Product\ProductService;

use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;

class ProductController extends Controller
{
    protected ProductService $service;

    public function __construct(ProductService $service)
    {
        $this->service = $service;
    }

    /**
     * Danh sách
     */
    public function index(Request $request): Response
    {
        $products = Product::query()

            ->with([
                'category',
                'brand',
                'unit',
            ])

            ->when(
                $request->search,
                function ($query) use ($request) {

                    $query->where(
                        'name',
                        'like',
                        '%' . $request->search . '%'
                    )

                    ->orWhere(
                        'sku',
                        'like',
                        '%' . $request->search . '%'
                    )

                    ->orWhere(
                        'barcode',
                        'like',
                        '%' . $request->search . '%'
                    );
                }
            )

            ->latest()

            ->paginate(10)

            ->withQueryString();

        return Inertia::render(
            'Products/Index',
            [

                'products' => $products,

                'filters' => [
                    'search' => $request->search,
                ],

            ]
        );
    }

    /**
     * Form tạo
     */
    public function create(): Response
    {
        return Inertia::render(
            'Products/Create',
            [

                'categories' => Category::query()
                    ->where('is_active', true)
                    ->get(),

                'brands' => Brand::query()
                    ->where('is_active', true)
                    ->get(),

                'units' => Unit::query()
                    ->where('is_active', true)
                    ->get(),

            ]
        );
    }

    /**
     * Lưu
     */
    public function store(
        StoreProductRequest $request
    ): RedirectResponse {

        $data = $request->validated();

        $data['slug'] = Str::slug(
            $data['name']
        );

        $this->service->create($data);

        return redirect()
            ->route('products.index')
            ->with(
                'success',
                'Tạo sản phẩm thành công'
            );
    }

    /**
     * Form sửa
     */
    public function edit(Product $product): Response
    {
        return Inertia::render(
            'Products/Edit',
            [

                'product' => $product,

                'categories' => Category::query()
                    ->where('is_active', true)
                    ->get(),

                'brands' => Brand::query()
                    ->where('is_active', true)
                    ->get(),

                'units' => Unit::query()
                    ->where('is_active', true)
                    ->get(),

            ]
        );
    }

    /**
     * Update
     */
    public function update(
        UpdateProductRequest $request,
        Product $product
    ): RedirectResponse {

        $data = $request->validated();

        $data['slug'] = Str::slug(
            $data['name']
        );

        $this->service->update(
            $product,
            $data
        );

        return redirect()
            ->route('products.index')
            ->with(
                'success',
                'Cập nhật thành công'
            );
    }

    /**
     * Xóa mềm
     */
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

    /**
     * Thùng rác
     */
    public function trash(): Response
    {
        $products = Product::onlyTrashed()

            ->latest()

            ->paginate(10);

        return Inertia::render(
            'Products/Trash',
            [
                'products' => $products,
            ]
        );
    }

    /**
     * Khôi phục
     */
    public function restore($id): RedirectResponse
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