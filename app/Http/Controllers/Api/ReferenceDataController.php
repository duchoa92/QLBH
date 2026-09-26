<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Supplier;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Role;

class ReferenceDataController extends Controller
{
    /**
     * Các dữ liệu nền dùng cho ô chọn trong hệ thống.
     */
    public function index(): JsonResponse
    {
        $categories = Category::query()
            ->with('categoryAttributes.values')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'attributes' => $category->categoryAttributes->map(fn ($attribute) => [
                    'id' => $attribute->id,
                    'name' => $attribute->name,
                    'values' => $attribute->values->map(fn ($value) => [
                        'value' => $value->value,
                    ])->values(),
                ])->values(),
            ])->values();

        return response()->json([
            'categories' => $categories,
            'brands' => Brand::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'category_id'])
                ->values(),
            'units' => Unit::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'short_name'])
                ->values(),
            'suppliers' => Supplier::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'phone'])
                ->values(),
            'roles' => Role::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->values(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
