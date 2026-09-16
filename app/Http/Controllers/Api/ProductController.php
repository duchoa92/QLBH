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
                ->where('status', ProductImei::STATUS_IN_STOCK)
                ->where('imei', 'like', "%{$imeiKeyword}%")
                ->pluck('product_id');

            $products = Product::query()
                ->with([
                    'unit:id,name,short_name',
                    'variants' => fn ($query) => $query
                        ->where('is_active', true)
                        ->select('id', 'product_id', 'sku', 'barcode', 'attributes', 'cost_price', 'sell_price', 'stock', 'is_active'),
                    'imeis' => fn ($query) => $query
                        ->with('variant:id,product_id,sku,barcode,attributes,cost_price,sell_price,stock')
                        ->where('status', ProductImei::STATUS_IN_STOCK)
                        ->select([
                            'id',
                            'product_id',
                            'variant_id',
                            'sell_price',
                        ]),
                ])
                ->withCount([
                    'imeis as available_imei_count' => fn ($query) =>
                        $query->where('status', ProductImei::STATUS_IN_STOCK),
                ])
                ->whereIn('id', $productIds)
                ->where('is_active', true)
                ->get()
                ->map(function ($product) {
                    $prices = $this->availableSellPrices($product);

                    return [
                        'id' => $product->id,
                        'name' => $product->name,
                        'sku' => $product->sku,
                        'barcode' => $product->barcode,
                        'cost_price' => $product->cost_price, // 👈 ĐÃ BỔ SUNG GIÁ NHẬP
                        'price' => $product->sell_price,      // Giữ giá bán cho frontend cũ
                        'sell_price' => $product->sell_price, // 👈 BỔ SUNG RÕ RÀNG GIÁ BÁN
                        'price_min' => $prices['min'],
                        'price_max' => $prices['max'],
                        'price_label' => $prices['label'],
                        'stock' => $product->manage_stock_by_serial || $product->product_type === 'imei'
                            ? $product->available_imei_count
                            : $product->stock,
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
                    'variants' => fn ($query) => $query
                        ->where('is_active', true)
                        ->select('id', 'product_id', 'sku', 'barcode', 'attributes', 'cost_price', 'sell_price', 'stock', 'is_active'),
                'imeis' => fn ($query) => $query
                    ->with('variant:id,product_id,sku,barcode,attributes,cost_price,sell_price,stock')
                    ->where('status', ProductImei::STATUS_IN_STOCK)
                    ->select([
                        'id',
                        'product_id',
                        'variant_id',
                        'sell_price',
                    ]),
            ])
            ->withCount([
                'imeis as available_imei_count' => fn ($query) =>
                    $query->where('status', ProductImei::STATUS_IN_STOCK),
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
                $prices = $this->availableSellPrices($product);

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'barcode' => $product->barcode,
                    'cost_price' => $product->cost_price, // 👈 ĐÃ BỔ SUNG GIÁ NHẬP
                    'price' => $product->sell_price,      // Giữ giá bán cho frontend cũ
                    'sell_price' => $product->sell_price, // 👈 BỔ SUNG RÕ RÀNG GIÁ BÁN
                    'price_min' => $prices['min'],
                    'price_max' => $prices['max'],
                    'price_label' => $prices['label'],
                    'stock' => $product->manage_stock_by_serial || $product->product_type === 'imei'
                        ? $product->available_imei_count
                        : $product->stock,
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

    private function availableSellPrices(Product $product): array
    {
        $prices = collect();

        if ($product->product_type === 'imei' || $product->manage_stock_by_serial) {
            $prices = $product->imeis
                ->map(function (ProductImei $imei) use ($product): float {
                    if ($imei->sell_price > 0) {
                        return (float) $imei->sell_price;
                    }

                    if ($imei->variant?->sell_price > 0) {
                        return (float) $imei->variant->sell_price;
                    }

                    return (float) $product->sell_price;
                })
                ->filter(fn (float $price): bool => $price > 0);
        } elseif ($product->relationLoaded('variants') && $product->variants->isNotEmpty()) {
            $prices = $product->variants
                ->filter(fn ($variant): bool => (int) ($variant->stock ?? 0) > 0)
                ->map(fn ($variant): float => (float) ($variant->sell_price ?: $product->sell_price))
                ->filter(fn (float $price): bool => $price > 0);
        }

        if ($prices->isEmpty() && $product->sell_price > 0) {
            $prices = collect([(float) $product->sell_price]);
        }

        $min = (float) ($prices->min() ?? 0);
        $max = (float) ($prices->max() ?? 0);

        return [
            'min' => $min,
            'max' => $max,
            'label' => $min > 0 && $max > 0 && $min !== $max
                ? number_format($min, 0, ',', '.') . ' - ' . number_format($max, 0, ',', '.')
                : number_format($min ?: $max, 0, ',', '.'),
        ];
    }

    public function getProductApi($id)
    {
        $product = \App\Models\Product::with([
            'variants' => fn ($query) => $query->where('is_active', true),
        ])->find($id);

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
            ->with('variant:id,product_id,sku,barcode,attributes,cost_price,sell_price,stock')
            ->where('status', ProductImei::STATUS_IN_STOCK)
            ->where(function ($query) {
                $query->whereNull('variant_id')
                    ->orWhereHas('variant', fn ($variantQuery) =>
                        $variantQuery->where('is_active', true)
                    );
            })
            ->when(
                $request->filled('variant_id'),
                fn ($query) => $query->where('variant_id', $request->input('variant_id'))
            )
            ->orderBy('imei')
            ->get([
                'id',
                'variant_id',
                'imei',
                'serial',
                'color',
                'storage',
                'cost_price',
                'sell_price',
            ])
            ->map(function (ProductImei $imei) use ($product): array {
                $effectiveSellPrice =
                    $imei->sell_price > 0
                        ? (float) $imei->sell_price
                        : ($imei->variant?->sell_price > 0
                            ? (float) $imei->variant->sell_price
                            : (float) $product->sell_price);

                $priceSource =
                    $imei->sell_price > 0
                        ? 'imei'
                        : ($imei->variant?->sell_price > 0 ? 'variant' : 'product');

                return [
                    'id' => $imei->id,
                    'variant_id' => $imei->variant_id,
                    'imei' => $imei->imei,
                    'serial' => $imei->serial,
                    'display_code' => $imei->imei ?: $imei->serial ?: 'IMEI #' . $imei->id,
                    'color' => $imei->color,
                    'storage' => $imei->storage,
                    'cost_price' => $imei->cost_price,
                    'sell_price' => $imei->sell_price,
                    'effective_sell_price' => $effectiveSellPrice,
                    'price' => $effectiveSellPrice,
                    'price_source' => $priceSource,
                    'variant' => $imei->variant
                        ? [
                            'id' => $imei->variant->id,
                            'sku' => $imei->variant->sku,
                            'barcode' => $imei->variant->barcode,
                            'attributes' => $imei->variant->attributes,
                            'cost_price' => $imei->variant->cost_price,
                            'sell_price' => $imei->variant->sell_price,
                            'price' => $imei->variant->sell_price,
                            'stock' => $imei->variant->stock,
                        ]
                        : null,
                ];
            })
            ->values();

        return response()->json($imeis);
    }
}
