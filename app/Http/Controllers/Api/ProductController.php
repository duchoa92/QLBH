<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImei;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $keyword = $request->string('keyword')->toString();

        if (str_starts_with(strtolower($keyword), 'imei/')) {
            $imeiKeyword = substr($keyword, 5);

            $productIds = ProductImei::query()
                ->where('status', ProductImei::STATUS_AVAILABLE)
                ->where('imei', 'like', "%{$imeiKeyword}%")
                ->pluck('product_id');

            $products = Product::query()
                ->with('unit:id,name,short_name')
                ->whereIn('id', $productIds)
                ->where('is_active', true)
                ->get()
                ->map(function ($product) {
                    return [
                        'id' => $product->id,
                        'name' => $product->name,
                        'sku' => $product->sku,
                        'barcode' => $product->barcode,
                        'cost_price' => $product->cost_price, // 👈 ĐÃ BỔ SUNG GIÁ NHẬP
                        'price' => $product->sell_price,      // Giữ giá bán cho frontend cũ
                        'sell_price' => $product->sell_price, // 👈 BỔ SUNG RÕ RÀNG GIÁ BÁN
                        'stock' => $product->stock,
                        'manage_stock_by_serial' => $product->manage_stock_by_serial,
                        'category_id' => $product->category_id,
                        'unit_id' => $product->unit_id,
                        'unit_name' => $product->unit?->short_name ?: $product->unit?->name,
                        'product_type' => $product->product_type,
                        'sold_count' => $product->sold_count ?? 0,
                        'image_url' => $product->image_url,
                    ];
                });

            return response()->json($products);
        }

        $categoryId = $request->input('category_id');

        $products = Product::query()
            ->with([
                'unit:id,name,short_name',
                'variants:id,product_id,sku,barcode,attributes,cost_price,sell_price,stock',
            ])
            ->where('is_active', true)
            ->when($keyword, function ($query) use ($keyword) {
                $normalizedKeyword = Product::normalizeSearch($keyword);

                $query->where(function ($sub) use ($keyword, $normalizedKeyword) {
                    $sub->where('barcode', $keyword)
                        ->orWhere('sku', 'like', $keyword . '%')
                        ->orWhere('search_text', 'like', '%' . $normalizedKeyword . '%')
                        ->orWhereHas('imeis', function ($q) use ($keyword) {
                            $q->where('imei', 'like', "%{$keyword}%");
                        });
                });
            })
            ->when($categoryId, function ($query) use ($categoryId) {
                $query->where('category_id', $categoryId);
            })
            ->orderByRaw(
                "
                CASE
                    WHEN barcode = ? THEN 1
                    WHEN sku = ? THEN 2
                    WHEN sku LIKE ? THEN 3
                    ELSE 4
                END
                ",
                [
                    $keyword,
                    $keyword,
                    $keyword . '%',
                ]
            )
            ->orderByDesc('sold_count')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'barcode' => $product->barcode,
                    'cost_price' => $product->cost_price, // 👈 ĐÃ BỔ SUNG GIÁ NHẬP
                    'price' => $product->sell_price,      // Giữ giá bán cho frontend cũ
                    'sell_price' => $product->sell_price, // 👈 BỔ SUNG RÕ RÀNG GIÁ BÁN
                    'stock' => $product->stock,
                    'manage_stock_by_serial' => $product->manage_stock_by_serial,
                    'category_id' => $product->category_id,
                    'unit_id' => $product->unit_id,
                    'unit_name' => $product->unit?->short_name ?: $product->unit?->name,
                    'product_type' => $product->product_type,
                    'sold_count' => $product->sold_count ?? 0,
                    'image_url' => $product->image_url,
                    'variants' => $product->variants
                        ->map(function ($variant) {
                            return [
                                'id' => $variant->id,
                                'sku' => $variant->sku,
                                'barcode' => $variant->barcode,
                                'attributes' => $variant->attributes,
                                'cost_price' => $variant->cost_price, // 👈 ĐẢM BẢO CÓ DÒNG NÀY (Giá nhập biến thể)
                                'sell_price' => $variant->sell_price, // 👈 Giá bán biến thể
                                'price' => $variant->sell_price,
                                'stock' => $variant->stock,
                            ];
                        })
                        ->values(),
                ];
            });

        return response()->json($products);
    }

    public function getProductApi($id)
    {
        $product = \App\Models\Product::with('variants')->find($id);

        if (!$product) {
            return response()->json([
                'message' => 'Không tìm thấy sản phẩm'
            ], 404);
        }

        return response()->json($product);
    }

    /**
     * Danh sách IMEI còn trong kho của 1 sản phẩm (dùng cho màn hình POS
     * khi người dùng bấm chọn sản phẩm quản lý theo IMEI).
     * Có thể lọc theo biến thể qua query `variant_id`.
     */
    public function imeis(Request $request, $id): JsonResponse
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'message' => 'Không tìm thấy sản phẩm'
            ], 404);
        }

        $imeis = $product->imeis()
            ->where('status', ProductImei::STATUS_AVAILABLE)
            ->when(
                $request->filled('variant_id'),
                fn ($query) => $query->where('variant_id', $request->input('variant_id'))
            )
            ->orderBy('imei')
            ->get([
                'id',
                'variant_id',
                'imei',
                'color',
                'storage',
                'sell_price',
            ]);

        return response()->json($imeis);
    }
}
