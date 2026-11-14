<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Repair;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleItemGift;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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

        // Nguồn thu: all (mặc định) | sales (bán hàng POS) | repair (sửa chữa đã hoàn tất/trả khách).
        $source = in_array(request('source'), ['sales', 'repair'], true) ? request('source') : 'all';
        $includeSales = $source !== 'repair';
        $includeRepair = $source !== 'sales';

        $base = Sale::query()
            ->where('status', '!=', 'cancelled')
            ->whereBetween('created_at', [$from, $to])
            ->when($paymentMethod !== '', fn ($query) => $query->where('payment_method', $paymentMethod))
            ->when($staffId > 0, fn ($query) => $query->where('user_id', $staffId));

        // Doanh thu sửa chữa tính theo ngày trả/hoàn tất, giống Dashboard.
        $repairDate = 'COALESCE(returned_at, completed_at, updated_at)';
        $repairBase = Repair::query()
            ->whereIn('status', ['done', 'returned'])
            ->whereRaw("{$repairDate} BETWEEN ? AND ?", [$from, $to])
            ->when($paymentMethod !== '', fn ($query) => $query->where('payment_method', $paymentMethod))
            ->when($staffId > 0, fn ($query) => $query->where('technician_id', $staffId));

        $salesRevenue = $includeSales ? (float) (clone $base)->sum('grand_total') : 0.0;
        $salesOrders = $includeSales ? (clone $base)->count() : 0;
        $repairRevenue = $includeRepair
            ? (float) (clone $repairBase)->selectRaw('COALESCE(SUM(COALESCE(final_cost, estimated_cost, 0)), 0) as total')->value('total')
            : 0.0;
        $repairOrders = $includeRepair ? (clone $repairBase)->count() : 0;

        $totalRevenue = $salesRevenue + $repairRevenue;
        $totalOrders = $salesOrders + $repairOrders;

        $summary = [
            'total_revenue' => $totalRevenue,
            'total_orders' => $totalOrders,
            'total_discount' => $includeSales ? (float) (clone $base)->sum('discount') : 0.0,
            'avg_order_value' => $totalOrders > 0
                ? round($totalRevenue / $totalOrders, 2)
                : 0,
            'sales_revenue' => $salesRevenue,
            'sales_orders' => $salesOrders,
            'repair_revenue' => $repairRevenue,
            'repair_orders' => $repairOrders,
        ];

        // Gộp theo ngày: mỗi ngày có doanh thu bán hàng, sửa chữa và tổng.
        $dailyMap = [];
        if ($includeSales) {
            foreach ((clone $base)
                ->selectRaw('DATE(created_at) as date, SUM(grand_total) as revenue, COUNT(*) as orders')
                ->groupBy('date')->get() as $row) {
                $dailyMap[$row->date] = [
                    'date' => $row->date,
                    'sales_revenue' => (float) $row->revenue,
                    'repair_revenue' => 0.0,
                    'orders' => (int) $row->orders,
                ];
            }
        }
        if ($includeRepair) {
            foreach ((clone $repairBase)
                ->selectRaw("DATE({$repairDate}) as date, SUM(COALESCE(final_cost, estimated_cost, 0)) as revenue, COUNT(*) as orders")
                ->groupBy('date')->get() as $row) {
                $dailyMap[$row->date] ??= ['date' => $row->date, 'sales_revenue' => 0.0, 'repair_revenue' => 0.0, 'orders' => 0];
                $dailyMap[$row->date]['repair_revenue'] = (float) $row->revenue;
                $dailyMap[$row->date]['orders'] += (int) $row->orders;
            }
        }
        ksort($dailyMap);
        $daily = collect($dailyMap)->map(fn ($row) => $row + ['revenue' => $row['sales_revenue'] + $row['repair_revenue']])->values();

        // Theo phương thức thanh toán: gộp hóa đơn bán hàng và phiếu sửa chữa.
        $paymentMap = [];
        if ($includeSales) {
            foreach ((clone $base)
                ->selectRaw('payment_method, SUM(grand_total) as revenue, COUNT(*) as orders')
                ->groupBy('payment_method')->get() as $row) {
                $paymentMap[$row->payment_method] = ['payment_method' => $row->payment_method, 'revenue' => (float) $row->revenue, 'orders' => (int) $row->orders];
            }
        }
        if ($includeRepair) {
            foreach ((clone $repairBase)
                ->selectRaw('payment_method, SUM(COALESCE(final_cost, estimated_cost, 0)) as revenue, COUNT(*) as orders')
                ->groupBy('payment_method')->get() as $row) {
                $key = $row->payment_method ?: 'cash';
                $paymentMap[$key] ??= ['payment_method' => $key, 'revenue' => 0.0, 'orders' => 0];
                $paymentMap[$key]['revenue'] += (float) $row->revenue;
                $paymentMap[$key]['orders'] += (int) $row->orders;
            }
        }
        $byPaymentMethod = collect($paymentMap)->sortByDesc('revenue')->values();

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
            ->withQueryString();

        // Giá trị tồn theo giá vốn thực: lô FIFO cho hàng thường, giá vốn từng máy cho IMEI.
        // products.cost_price chỉ là giá nhập gần nhất nên chỉ dùng cho phần tồn chưa thuộc lô nào.
        $stockValues = $this->stockValuesFor($products->getCollection()->pluck('id')->all());
        $products->through(function ($product) use ($stockValues) {
            $product->stock_value = (float) ($stockValues[$product->id] ?? 0);
            return $product;
        });

        $activeProducts = Product::where('is_active', true);

        $summary = [
            'total_products' => (clone $activeProducts)->count(),
            'total_stock_qty' => (int) (clone $activeProducts)->sum('stock'),
            'total_stock_value' => $this->totalStockValue(),
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
                COALESCE(SUM((CASE WHEN sale_items.unit_cost IS NOT NULL THEN sale_items.unit_cost * sale_items.quantity ELSE COALESCE(sale_items.base_quantity, sale_items.quantity) * products.cost_price END)), 0) as cost,
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
            ->selectRaw('COALESCE(SUM((CASE WHEN sale_item_gifts.unit_cost IS NOT NULL THEN sale_item_gifts.unit_cost * sale_item_gifts.quantity ELSE COALESCE(sale_item_gifts.base_quantity, sale_item_gifts.quantity) * products.cost_price END)), 0) as cost')
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
            ->selectRaw('DATE(sales.created_at) as date, SUM((CASE WHEN sale_item_gifts.unit_cost IS NOT NULL THEN sale_item_gifts.unit_cost * sale_item_gifts.quantity ELSE COALESCE(sale_item_gifts.base_quantity, sale_item_gifts.quantity) * products.cost_price END)) as cost')
            ->groupBy('date')
            ->get()
            ->keyBy('date');

        $daily = (clone $base)
            ->selectRaw('
                DATE(sales.created_at) as date,
                SUM(sale_items.subtotal) as revenue,
                SUM((CASE WHEN sale_items.unit_cost IS NOT NULL THEN sale_items.unit_cost * sale_items.quantity ELSE COALESCE(sale_items.base_quantity, sale_items.quantity) * products.cost_price END)) as cost
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
                SUM((CASE WHEN sale_items.unit_cost IS NOT NULL THEN sale_items.unit_cost * sale_items.quantity ELSE COALESCE(sale_items.base_quantity, sale_items.quantity) * products.cost_price END)) as cost
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
            ->selectRaw('products.id, products.name, SUM(sale_item_gifts.quantity) as qty_sold, SUM((CASE WHEN sale_item_gifts.unit_cost IS NOT NULL THEN sale_item_gifts.unit_cost * sale_item_gifts.quantity ELSE COALESCE(sale_item_gifts.base_quantity, sale_item_gifts.quantity) * products.cost_price END)) as cost')
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
    /** @param  array<int, int>  $productIds  @return array<int, float> */
    private function stockValuesFor(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $batches = DB::table('product_stock_batches')
            ->whereIn('product_id', $productIds)
            ->selectRaw('product_id, SUM(remaining_quantity) as qty, SUM(remaining_quantity * cost_price) as value')
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id');

        $imeis = DB::table('product_imeis')
            ->whereNull('deleted_at')
            ->whereIn('product_id', $productIds)
            ->where('status', 'in_stock')
            ->selectRaw('product_id, SUM(COALESCE(cost_price, 0)) as value')
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id');

        $values = [];
        foreach (DB::table('products')->whereIn('id', $productIds)->get(['id', 'stock', 'cost_price', 'manage_stock_by_serial', 'product_type']) as $product) {
            if ($product->manage_stock_by_serial || $product->product_type === 'imei') {
                $values[$product->id] = (float) ($imeis[$product->id]->value ?? 0);
                continue;
            }

            $batchQty = (int) ($batches[$product->id]->qty ?? 0);
            $values[$product->id] = (float) ($batches[$product->id]->value ?? 0)
                + max(0, (int) $product->stock - $batchQty) * (float) $product->cost_price;
        }

        return $values;
    }

    private function totalStockValue(): float
    {
        $batchValue = (float) DB::table('product_stock_batches as b')
            ->join('products as p', 'p.id', '=', 'b.product_id')
            ->where('p.is_active', true)
            ->whereNull('p.deleted_at')
            ->sum(DB::raw('b.remaining_quantity * b.cost_price'));

        $imeiValue = (float) DB::table('product_imeis as i')
            ->join('products as p', 'p.id', '=', 'i.product_id')
            ->where('p.is_active', true)
            ->whereNull('p.deleted_at')
            ->whereNull('i.deleted_at')
            ->where('i.status', 'in_stock')
            ->sum(DB::raw('COALESCE(i.cost_price, 0)'));

        $uncoveredValue = (float) DB::table('products as p')
            ->leftJoinSub(
                DB::table('product_stock_batches')->selectRaw('product_id, SUM(remaining_quantity) as qty')->groupBy('product_id'),
                'b',
                'b.product_id',
                '=',
                'p.id'
            )
            ->where('p.is_active', true)
            ->whereNull('p.deleted_at')
            ->where('p.manage_stock_by_serial', false)
            ->where('p.product_type', '!=', 'imei')
            ->sum(DB::raw('CASE WHEN p.stock > COALESCE(b.qty, 0) THEN (p.stock - COALESCE(b.qty, 0)) * p.cost_price ELSE 0 END'));

        return $batchValue + $imeiValue + $uncoveredValue;
    }
}
