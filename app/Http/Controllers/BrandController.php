<?php

namespace App\Http\Controllers;

use App\Http\Requests\Brand\StoreBrandRequest;
use App\Http\Requests\Brand\UpdateBrandRequest;
use App\Models\Brand;
use App\Services\Brand\BrandService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BrandController extends Controller
{
    public function __construct(
        protected BrandService $service
    ) {}

    // Hiển thị danh sách thương hiệu
    public function index(): Response
    {
        return Inertia::render(
            'Brands/Index',
            [
                'brands' => $this->service
                    ->paginate(),
            ]
        );
    }

    // Hiển thị form tạo thương hiệu
    public function create(): Response
    {
        return Inertia::render(
            'Brands/Create'
        );
    }

    // Xử lý tạo mới thương hiệu
    public function store(
        StoreBrandRequest $request
    ): RedirectResponse {

        $this->service->create(
            $request->validated()
        );

        return redirect()
            ->route('brands.index')
            ->with(
                'success',
                'Thêm thương hiệu thành công'
            );
    }

    // Hiển thị form chỉnh sửa thương hiệu
    public function edit(
        Brand $brand
    ): Response {

        return Inertia::render(
            'Brands/Edit',
            [
                'brand' => $brand,
            ]
        );
    }

    // Xử lý cập nhật thương hiệu
    public function update(
        UpdateBrandRequest $request,
        Brand $brand
    ): RedirectResponse {

        $this->service->update(
            $brand,
            $request->validated()
        );

        return redirect()
            ->route('brands.index')
            ->with(
                'success',
                'Cập nhật thành công'
            );
    }

    // Xử lý xóa thương hiệu
    public function destroy(
        Brand $brand
    ): RedirectResponse {

        $this->service->delete(
            $brand
        );

        return redirect()
            ->back()
            ->with(
                'success',
                'Xóa thành công'
            );
    }

    // Xử lý khôi phục thương hiệu đã xóa
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

    // Hiển thị thương hiệu đã xóa
    public function trash(): Response
    {
        return Inertia::render(
            'Brands/Trash',
            [
                'brands' => $this->service
                    ->trash(),
            ]
        );
    }
}