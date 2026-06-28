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
use App\Models\Brand;
use App\Repositories\Product\ProductRepository;


class ProductController extends Controller
{
    public function __construct(
    protected ProductService $service,
    protected ProductRepository $productRepository
) {}

    public function index()
    {
        return Inertia::render('Products/Index', [
            'products' => $this->productRepository->paginate(),

            'filters' => request()->only([
                'search',
                'category_id',
                'brand_id',
                'stock'
            ]),

            'categories' => Category::select('id','name')->get(),
            
            'brands' => Brand::query()
                ->select('id','name','category_id')
                ->when(request('category_id'), function ($q) {
                    $q->where('category_id', request('category_id'));
                })
                ->orderBy('name')
                ->get(),
        ]);
    }

    // Hiển thị chi tiết sản phẩm
    public function show(
        Product $product
    ): Response {

        $product->load([
            'category',
            'brand',
            'imeis',
        ]);

        return Inertia::render(
            'Products/Show',
            [
                'product' => $product,
            ]
        );
    }

    // hiển thị form tạo sản phẩm
    public function create(): Response
    {
        return Inertia::render(
            'Products/Create',
            [

                'brands' => Brand::query()
                    ->select(
                        'id',
                        'name'
                    )
                    ->orderBy('name')
                    ->get(),

                'categories' => Category::query()
                    ->select(
                        'id',
                        'name'
                    )
                    ->orderBy('name')
                    ->get(),
            ]
        );
    }

    // Hiển thị form chỉnh sửa sản phẩm
    public function edit(
        Product $product
    ): Response {

        return Inertia::render(
            'Products/Edit',
            [

                'product' => $product,

                'brands' => Brand::query()
                    ->select(
                        'id',
                        'name'
                    )
                    ->orderBy('name')
                    ->get(),

                'categories' => Category::query()
                    ->select(
                        'id',
                        'name'
                    )
                    ->orderBy('name')
                    ->get(),
            ]
        );
    }

    // Lưu sản phẩm mới
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



   // Chuyển vào thùng rác
    public function destroy(
        Product $product
    ): RedirectResponse {

        $this->service->delete($product);

        return redirect()
            ->back()
            ->with(
                'success',
                'Đã chuyển vào thùng rác!'
            );
    }

    // Thùng rác
    public function trash(): Response
    {
        return Inertia::render('Products/Trash', [
            'products' => $this->productRepository->trash()
        ]);
    }

    // Khôi phục sản phẩm
    public function restore(int $id): RedirectResponse
    {
        Product::onlyTrashed()->findOrFail($id)->restore();

        return back()->with('success', 'Đã khôi phục');
    }

    // Kiểm tra an toàn trước khi xóa
    public function forceDelete(int $id)
    {
        $product = Product::withTrashed()->findOrFail($id);

        if (!$product->canForceDelete()) {
            return back()->with('error', 'Sản phẩm đã phát sinh dữ liệu, không thể xóa vĩnh viễn');
        }

        $product->forceDelete();

        return back()->with('success', 'Đã xóa vĩnh viễn');
    }

    // Bulk restore
    public function bulkRestore()
    {
        request()->validate([
            'ids' => 'required|array'
        ]);

        Product::onlyTrashed()
            ->whereIn('id', request('ids'))
            ->restore();

        return back()->with('success', 'Khôi phục thành công');
    }

    public function bulkForceDelete()
    {
        request()->validate([
            'ids' => 'required|array'
        ]);

        $products = Product::withTrashed()
            ->whereIn('id', request('ids'))
            ->get();

        foreach ($products as $product) {
            if ($product->canForceDelete()) {
                $product->forceDelete();
            }
        }

        return back()->with('success', 'Đã xóa vĩnh viễn');
    }

    // Xóa nhiều SP
    public function bulkDelete()
    {
        request()->validate([
            'ids' => 'required|array'
        ]);

        Product::whereIn('id', request('ids'))->delete();

        return back()->with('success', 'Đã chuyển vào thùng rác');
    }


    // In tem
    public function printImei()
    {
        $ids = request('ids', []);

        $products = Product::with('imeis')
            ->whereIn('id', $ids)
            ->get();

        return Inertia::render('Products/PrintImei', [
            'products' => $products
        ]);
    }
}