<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleItemGift;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    /**
     * Trang tổng hợp Báo cáo (giao diện, dữ liệu load qua API bên dưới).
     */
    public function index(): Response
    {
        return Inertia::render('Reports/Index', [
            // Dữ liệu cho các ô lọc (danh mục, thương hiệu, nhân viên bán hàng).
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name']),
            'staff' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** Mức nợ: [lớn hơn, nhỏ hơn hoặc bằng] — null là không giới hạn. */
    private const DEBT_LEVELS = [
        'lt1m' => [0, 1_000_000],
        '1m_5m' => [1_000_000, 5_000_000],
        '5m_20m' => [5_000_000, 20_000_000],
        'gt20m' => [20_000_000, null],
    ];

    /**
     * Lọc theo sản phẩm / danh mục / nhân viên bán cho báo cáo lợi nhuận.
     * Truy vấn truyền vào phải đã join `products` và `sales`.
     */
    private function applyProfitFilters($query): void
    {
        $keyword = trim((string) request('search', ''));
        $categoryId = (int) request('category_id');
        $staffId = (int) request('user_id');

        if ($keyword !== '') {
            $query->where('products.name', 'like', "%{$keyword}%");
        }
        if ($categoryId > 0) {
            $query->where('products.category_id', $categoryId);
        }
        if ($staffId > 0) {
            $query->where('sales.user_id', $staffId);
        }
    }

    /**
     * Lấy khoảng thời gian lọc từ request (mặc định: từ đầu tháng tới hiện tại).
     */
    private function dateRange(): array
    {
        $from = request('from')
            ? Carbon::parse(request('from'))->startOfDay()
            : now()->startOfMonth();

        $to = request('to')
            ? Carbon::parse(request('to'))->endOfDay()
            : now()->endOfDay();

        return [$from, $to];
    }

    /*
    |--------------------------------------------------------------------------
    | Báo cáo doanh thu
    |--------------------------------------------------------------------------
    */
    public function revenue(): JsonResponse
    {
        [$from, $to] = $this->dateRange();

        $paymentMethod = in_array(request('payment_method'), ['cash', 'bank', 'mixed', 'card'], true)
            ? request('payment_method') : '';
        $staffId = (int) request('user_id');

        $base = Sale::query()
            ->where('status', '!=', 'cancelled')
            ->whereBetween('created_at', [$from, $to])
            ->when($paymentMethod !== '', fn ($query) => $query->where('payment_method', $paymentMethod))
            ->when($staffId > 0, fn ($query) => $query->where('user_id', $staffId));

        $totalRevenue = (clone $base)->sum('grand_total');
        $totalOrders = (clone $base)->count();

        $summary = [
            'total_revenue' => (float) $totalRevenue,
            'total_orders' => $totalOrders,
            'total_discount' => (float) (clone $base)->sum('discount'),
            'avg_order_value' => $totalOrders > 0
                ? round($totalRevenue / $totalOrders, 2)
                : 0,
        ];

        $daily = (clone $base)
            ->selectRaw('DATE(created_at) as date, SUM(grand_total) as revenue, COUNT(*) as orders')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $byPaymentMethod = (clone $base)
            ->selectRaw('payment_method, SUM(grand_total) as revenue, COUNT(*) as orders')
            ->groupBy('payment_method')
            ->orderByDesc('revenue')
            ->get();

        return response()->json([
            'summary' => $summary,
            'daily' => $daily,
            'by_payment_method' => $byPaymentMethod,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Báo cáo hàng bán chạy / bán chậm
    |--------------------------------------------------------------------------
    */
    public function bestSellers(): JsonResponse
    {
        [$from, $to] = $this->dateRange();

        $limit = in_array((int) request('limit', 10), [10, 20, 50], true) ? (int) request('limit', 10) : 10;
        $order = request('order', 'best'); // best | worst

        $items = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.status', '!=', 'cancelled')
            ->whereBetween('sales.created_at', [$from, $to])
            ->when(trim((string) request('search', '')) !== '', function ($query) {
                $keyword = trim((string) request('search'));
                $query->where(fn ($sub) => $sub
                    ->where('products.name', 'like', "%{$keyword}%")
                    ->orWhere('products.sku', 'like', "%{$keyword}%"));
            })
            ->when((int) request('category_id') > 0, fn ($query) => $query->where('products.category_id', (int) request('category_id')))
            ->when((int) request('brand_id') > 0, fn ($query) => $query->where('products.brand_id', (int) request('brand_id')))
            ->selectRaw('
                products.id,
                products.name,
                products.sku,
                products.image,
                products.stock,
                SUM(sale_items.quantity) as qty_sold,
                SUM(sale_items.subtotal) as revenue
            ')
            ->groupBy('products.id', 'products.name', 'products.sku', 'products.image', 'products.stock')
            ->orderBy('qty_sold', $order === 'worst' ? 'asc' : 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                $row->image_url = $row->image ? asset('storage/' . $row->image) : null;
                return $row;
            });

        return response()->json([
            'items' => $items,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Báo cáo tồn kho
    |--------------------------------------------------------------------------
    */
    public function inventory(): JsonResponse
    {
        $alertsEnabled = filter_var(setting('inventory_alert_enabled', true), FILTER_VALIDATE_BOOLEAN);
        $defaultThreshold = max(0, (int) setting('inventory_alert_threshold', 5));
        $lowStock = function ($query) use ($defaultThreshold) {
            $query->where(function ($conditions) use ($defaultThreshold) {
                $conditions->where(function ($explicit) {
                    $explicit->where('alert_stock', '>', 0)->whereColumn('stock', '<=', 'alert_stock');
                });
                if ($defaultThreshold > 0) {
                    $conditions->orWhere(function ($fallback) use ($defaultThreshold) {
                        $fallback->where('alert_stock', 0)->where('stock', '<=', $defaultThreshold);
                    });
                }
            });
        };
        $query = Product::query()->where('is_active', true);

        $stockStatus = in_array(request('stock_status'), ['low', 'out', 'in'], true) ? request('stock_status') : '';
        if (request()->boolean('low_stock') || $stockStatus === 'low') {
            $alertsEnabled ? $query->where($lowStock) : $query->whereRaw('1 = 0');
        } elseif ($stockStatus === 'out') {
            $query->where('stock', '<=', 0);
        } elseif ($stockStatus === 'in') {
            $query->where('stock', '>', 0);
        }

        if ((int) request('category_id') > 0) {
            $query->where('category_id', (int) request('category_id'));
        }
        if ((int) request('brand_id') > 0) {
            $query->where('brand_id', (int) request('brand_id'));
        }

        if (request('search')) {
            $keyword = request('search');

            $query->where(function ($sub) use ($keyword) {
                $sub->where('name', 'like', "%{$keyword}%")
                    ->orWhere('sku', 'like', "%{$keyword}%")
                    ->orWhere('model', 'like', "%{$keyword}%")
                    ->orWhereHas('variants', fn ($variantQuery) => $variantQuery
                        ->where('barcode', 'like', "%{$keyword}%")
                        ->orWhere('sku', 'like', "%{$keyword}%"));
            });
        }

        $products = $query
            ->with(['category:id,name', 'brand:id,name'])
            ->select('id', 'category_id', 'brand_id', 'name', 'sku', 'image', 'stock', 'alert_stock', 'cost_price', 'sell_price')
            ->orderBy('stock')
            ->paginate(20)
            ->withQueryString()
            ->through(function ($product) {
                $product->stock_value = (float) $product->stock * (float) $product->cost_price;
                return $product;
            });

        $activeProducts = Product::where('is_active', true);

        $summary = [
            'total_products' => (clone $activeProducts)->count(),
            'total_stock_qty' => (int) (clone $activeProducts)->sum('stock'),
            'total_stock_value' => (float) (clone $activeProducts)->selectRaw('COALESCE(SUM(stock * cost_price), 0) as v')->value('v'),
            'low_stock_count' => $alertsEnabled ? (clone $activeProducts)->where($lowStock)->count() : 0,
        ];

        return response()->json([
            'products' => $products,
            'summary' => $summary,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Báo cáo công nợ (khách hàng / nhà cung cấp)
    |--------------------------------------------------------------------------
    */
    public function debts(): JsonResponse
    {
        $type = request('type', 'customer'); // customer | supplier
        $keyword = trim((string) request('search', ''));
        $level = array_key_exists((string) request('level'), self::DEBT_LEVELS) ? (string) request('level') : '';
        $applyLevel = function ($query) use ($level) {
            if ($level === '') {
                return;
            }
            [$min, $max] = self::DEBT_LEVELS[$level];
            $query->where('debt_balance', '>', $min);
            if ($max !== null) {
                $query->where('debt_balance', '<=', $max);
            }
        };

        if ($type === 'supplier') {
            $query = Supplier::query()->where('debt_balance', '>', 0);

            if ($keyword !== '') {
                $query->where(fn ($sub) => $sub
                    ->where('name', 'like', "%{$keyword}%")
                    ->orWhere('code', 'like', "%{$keyword}%")
                    ->orWhere('phone', 'like', "%{$keyword}%"));
            }
            $applyLevel($query);

            $items = $query
                ->select('id', 'code', 'name', 'phone', 'debt_balance')
                ->orderByDesc('debt_balance')
                ->paginate(20)
                ->withQueryString();

            $summary = [
                'total_debt' => (float) Supplier::sum('debt_balance'),
                'count' => Supplier::where('debt_balance', '>', 0)->count(),
            ];

            return response()->json([
                'items' => $items,
                'summary' => $summary,
            ]);
        }

        $query = Customer::query()->where('debt_balance', '>', 0);

        if ($keyword !== '') {
            $query->where(fn ($sub) => $sub
                ->where('full_name', 'like', "%{$keyword}%")
                ->orWhere('code', 'like', "%{$keyword}%")
                ->orWhere('phone', 'like', "%{$keyword}%"));
        }
        $applyLevel($query);

        $items = $query
            ->select('id', 'code', 'full_name', 'phone', 'debt_balance')
            ->orderByDesc('debt_balance')
            ->paginate(20)
            ->withQueryString();

        $summary = [
            'total_debt' => (float) Customer::sum('debt_balance'),
            'count' => Customer::where('debt_balance', '>', 0)->count(),
        ];

        return response()->json([
            'items' => $items,
            'summary' => $summary,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Báo cáo lợi nhuận
    |--------------------------------------------------------------------------
    */
    public function profit(): JsonResponse
    {
        [$from, $to] = $this->dateRange();

        $base = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.status', '!=', 'cancelled')
            ->whereBetween('sales.created_at', [$from, $to])
            ->tap(fn ($query) => $this->applyProfitFilters($query));

        $summaryRow = (clone $base)
            ->selectRaw('
                COALESCE(SUM(sale_items.subtotal), 0) as revenue,
                COALESCE(SUM(COALESCE(sale_items.base_quantity, sale_items.quantity) * products.cost_price), 0) as cost,
                COALESCE(SUM(sale_items.quantity), 0) as qty
            ')
            ->first();

        $revenue = (float) $summaryRow->revenue;
        $giftCost = SaleItemGift::query()
            ->join('sale_items', 'sale_items.id', '=', 'sale_item_gifts.sale_item_id')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_item_gifts.product_id')
            ->where('sales.status', '!=', 'cancelled')
            ->whereBetween('sales.created_at', [$from, $to])
            ->tap(fn ($query) => $this->applyProfitFilters($query))
            ->selectRaw('COALESCE(SUM(COALESCE(sale_item_gifts.base_quantity, sale_item_gifts.quantity) * products.cost_price), 0) as cost')
            ->value('cost');
        $cost = (float) $summaryRow->cost + (float) $giftCost;
        $profit = $revenue - $cost;

        $giftCostsByDay = SaleItemGift::query()
            ->join('sale_items', 'sale_items.id', '=', 'sale_item_gifts.sale_item_id')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_item_gifts.product_id')
            ->where('sales.status', '!=', 'cancelled')
            ->whereBetween('sales.created_at', [$from, $to])
            ->tap(fn ($query) => $this->applyProfitFilters($query))
            ->selectRaw('DATE(sales.created_at) as date, SUM(COALESCE(sale_item_gifts.base_quantity, sale_item_gifts.quantity) * products.cost_price) as cost')
            ->groupBy('date')
            ->get()
            ->keyBy('date');

        $daily = (clone $base)
            ->selectRaw('
                DATE(sales.created_at) as date,
                SUM(sale_items.subtotal) as revenue,
                SUM(COALESCE(sale_items.base_quantity, sale_items.quantity) * products.cost_price) as cost
            ')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function ($row) use ($giftCostsByDay) {
                $row->revenue = (float) $row->revenue;
                $row->cost = (float) $row->cost + (float) ($giftCostsByDay[$row->date]->cost ?? 0);
                $row->profit = $row->revenue - $row->cost;
                return $row;
            });

        $byProduct = (clone $base)
            ->selectRaw('
                products.id,
                products.name,
                SUM(sale_items.quantity) as qty_sold,
                SUM(sale_items.subtotal) as revenue,
                SUM(COALESCE(sale_items.base_quantity, sale_items.quantity) * products.cost_price) as cost
            ')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('revenue')
            ->limit(15)
            ->get()
            ->keyBy('id');

        $giftCostsByProduct = SaleItemGift::query()
            ->join('sale_items', 'sale_items.id', '=', 'sale_item_gifts.sale_item_id')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_item_gifts.product_id')
            ->where('sales.status', '!=', 'cancelled')
            ->whereBetween('sales.created_at', [$from, $to])
            ->tap(fn ($query) => $this->applyProfitFilters($query))
            ->selectRaw('products.id, products.name, SUM(sale_item_gifts.quantity) as qty_sold, SUM(COALESCE(sale_item_gifts.base_quantity, sale_item_gifts.quantity) * products.cost_price) as cost')
            ->groupBy('products.id', 'products.name')
            ->get();

        foreach ($giftCostsByProduct as $giftRow) {
            if ($byProduct->has($giftRow->id)) {
                $byProduct[$giftRow->id]->cost = (float) $byProduct[$giftRow->id]->cost + (float) $giftRow->cost;
                $byProduct[$giftRow->id]->qty_sold = (int) $byProduct[$giftRow->id]->qty_sold + (int) $giftRow->qty_sold;
            } else {
                $byProduct->put($giftRow->id, (object) [
                    'id' => $giftRow->id,
                    'name' => $giftRow->name,
                    'qty_sold' => (int) $giftRow->qty_sold,
                    'revenue' => 0.0,
                    'cost' => (float) $giftRow->cost,
                ]);
            }
        }

        $byProduct = $byProduct->map(function ($row) {
                $row->revenue = (float) $row->revenue;
                $row->cost = (float) $row->cost;
                $row->profit = $row->revenue - $row->cost;
                return $row;
            })
            ->sortByDesc('revenue')
            ->take(15)
            ->values();

        return response()->json([
            'summary' => [
                'revenue' => $revenue,
                'cost' => $cost,
                'profit' => $profit,
                'qty_sold' => (int) $summaryRow->qty,
                'margin_percent' => $revenue > 0
                    ? round($profit / $revenue * 100, 2)
                    : 0,
            ],
            'daily' => $daily,
            'by_product' => $byProduct,
        ]);
    }
}
