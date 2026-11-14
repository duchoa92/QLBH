<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Repair;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleItemGift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Nhãn trạng thái phiếu sửa chữa (dùng để hiển thị ở bảng giao dịch gần đây).
     */
    private const REPAIR_STATUS_LABELS = [

        'pending' => 'Tiếp nhận',
        'repairing' => 'Đang sửa',
        'done' => 'Hoàn tất sửa',
        'returned' => 'Đã trả khách',
        'cancelled' => 'Hủy',
    ];

    /**
     * Các trạng thái phiếu sửa được coi là đã phát sinh doanh thu.
     */
    private const REPAIR_REVENUE_STATUSES = [
        'done',
        'returned',
    ];

    public function index(): Response
    {
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();

        $yesterdayStart = now()->subDay()->startOfDay();
        $yesterdayEnd = now()->subDay()->endOfDay();

        $todayRevenue = $this->revenueBetween($todayStart, $todayEnd);
        $yesterdayRevenue = $this->revenueBetween($yesterdayStart, $yesterdayEnd);

        $revenueChangePercent = $yesterdayRevenue > 0
            ? round((($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100, 1)
            : ($todayRevenue > 0 ? 100.0 : 0.0);

        $todayProfit = $this->profitBetween($todayStart, $todayEnd);

        /*
        |--------------------------------------------------------------------------
        | Tiền mặt / Chuyển khoản (theo hóa đơn POS hôm nay)
        |--------------------------------------------------------------------------
        */

        $salesToday = Sale::query()
            ->where('status', '!=', 'cancelled')
            ->whereBetween('created_at', [$todayStart, $todayEnd]);

        $cashAmount = (float) (clone $salesToday)
            ->selectRaw('COALESCE(SUM(CASE WHEN cash_received_amount > change_amount THEN cash_received_amount - change_amount ELSE 0 END), 0) as total')
            ->value('total');

        $bankAmount = (float) (clone $salesToday)
            ->sum('bank_received_amount');
        $bankAmount += (float) (clone $salesToday)->where('payment_method', 'card')->sum('paid_amount');

        /*
        |--------------------------------------------------------------------------
        | Công nợ khách hàng
        |--------------------------------------------------------------------------
        */

        $totalDebt = (float) Customer::sum('debt_balance');

        $debtorCount = Customer::where('debt_balance', '>', 0)->count();

        $topDebtors = Customer::query()
            ->where('debt_balance', '>', 0)
            ->orderByDesc('debt_balance')
            ->limit(5)
            ->get(['id', 'full_name', 'phone', 'debt_balance'])
            ->map(fn ($customer) => [
                'id' => $customer->id,
                'name' => $customer->full_name,
                'phone' => $customer->phone,
                'debt' => (float) $customer->debt_balance,
            ]);

        return Inertia::render('Dashboard', [

            'stats' => [
                'todayRevenue' => $todayRevenue,
                'revenueChangePercent' => $revenueChangePercent,
                'todayProfit' => $todayProfit,
                'cashAmount' => $cashAmount,
                'bankAmount' => $bankAmount,
                'totalDebt' => $totalDebt,
                'debtorCount' => $debtorCount,
            ],

            'expenseReminders' => ExpenseController::dashboardReminders(),

            'todos' => $this->todos(),

            'topDebtors' => $topDebtors,

            'recentOrders' => $this->recentOrders(),

            'revenueChart' => [
                'week' => $this->dailyChart(now()->subDays(6)->startOfDay(), $todayEnd),
                'month' => $this->dailyChart(now()->startOfMonth(), $todayEnd),
            ],

            'revenueBreakdown' => $this->revenueBreakdown(
                now()->startOfMonth(),
                $todayEnd
            ),
        ]);
    }

    /**
     * Biểu đồ doanh thu - lợi nhuận theo từng ngày cho một khoảng ngày tùy chọn (chọn từ bộ lọc ngày).
     */
    public function chart(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ], [
            'from.required' => 'Vui lòng chọn ngày bắt đầu.',
            'to.required' => 'Vui lòng chọn ngày kết thúc.',
            'from.date_format' => 'Ngày bắt đầu không hợp lệ.',
            'to.date_format' => 'Ngày kết thúc không hợp lệ.',
            'to.after_or_equal' => 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.',
        ]);

        $from = Carbon::createFromFormat('Y-m-d', $data['from'])->startOfDay();
        $to = Carbon::createFromFormat('Y-m-d', $data['to'])->endOfDay();

        // Giới hạn 366 ngày để biểu đồ và truy vấn không quá nặng.
        if ($from->diffInDays($to) > 365) {
            $from = $to->copy()->subDays(365)->startOfDay();
        }

        return response()->json($this->dailyChart($from, $to));
    }

    /**
     * Tổng doanh thu (bán hàng POS + sửa chữa đã trả khách) trong khoảng thời gian.
     */
    private function revenueBetween(Carbon $from, Carbon $to): float
    {
        $posRevenue = (float) Sale::query()
            ->where('status', '!=', 'cancelled')
            ->whereBetween('created_at', [$from, $to])
            ->sum('grand_total');

        $repairRevenue = (float) Repair::query()
            ->whereIn('status', self::REPAIR_REVENUE_STATUSES)
            ->whereRaw(
                'COALESCE(returned_at, completed_at, updated_at) BETWEEN ? AND ?',
                [$from, $to]
            )
            ->selectRaw('COALESCE(SUM(COALESCE(final_cost, estimated_cost, 0)), 0) as total')
            ->value('total');

        return $posRevenue + $repairRevenue;
    }

    /**
     * Lợi nhuận gộp ước tính: (giá bán - giá vốn) cho hàng POS
     * + toàn bộ tiền công sửa chữa (chưa tách được giá vốn linh kiện).
     */
    private function profitBetween(Carbon $from, Carbon $to): float
    {
        $posRow = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.status', '!=', 'cancelled')
            ->whereBetween('sales.created_at', [$from, $to])
            ->selectRaw('
                COALESCE(SUM(sale_items.subtotal), 0) as revenue,
                COALESCE(SUM((CASE WHEN sale_items.unit_cost IS NOT NULL THEN sale_items.unit_cost * sale_items.quantity ELSE COALESCE(sale_items.base_quantity, sale_items.quantity) * products.cost_price END)), 0) as cost
            ')
            ->first();

        $giftCost = SaleItemGift::query()
            ->join('sale_items', 'sale_items.id', '=', 'sale_item_gifts.sale_item_id')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_item_gifts.product_id')
            ->where('sales.status', '!=', 'cancelled')
            ->whereBetween('sales.created_at', [$from, $to])
            ->selectRaw('COALESCE(SUM((CASE WHEN sale_item_gifts.unit_cost IS NOT NULL THEN sale_item_gifts.unit_cost * sale_item_gifts.quantity ELSE COALESCE(sale_item_gifts.base_quantity, sale_item_gifts.quantity) * products.cost_price END)), 0) as cost')
            ->value('cost');

        $posProfit = (float) $posRow->revenue - (float) $posRow->cost - (float) $giftCost;

        $repairProfit = (float) Repair::query()
            ->whereIn('status', self::REPAIR_REVENUE_STATUSES)
            ->whereRaw(
                'COALESCE(returned_at, completed_at, updated_at) BETWEEN ? AND ?',
                [$from, $to]
            )
            ->selectRaw('COALESCE(SUM(COALESCE(final_cost, estimated_cost, 0)), 0) as total')
            ->value('total');

        return $posProfit + $repairProfit;
    }

    /**
     * Doanh thu + lợi nhuận theo từng ngày trong khoảng [from, to].
     */
    private function dailyChart(Carbon $from, Carbon $to): array
    {
        $salesByDay = Sale::query()
            ->where('status', '!=', 'cancelled')
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('DATE(created_at) as date, SUM(grand_total) as revenue')
            ->groupBy('date')
            ->pluck('revenue', 'date');

        $costByDay = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.status', '!=', 'cancelled')
            ->whereBetween('sales.created_at', [$from, $to])
            ->selectRaw('DATE(sales.created_at) as date, SUM((CASE WHEN sale_items.unit_cost IS NOT NULL THEN sale_items.unit_cost * sale_items.quantity ELSE COALESCE(sale_items.base_quantity, sale_items.quantity) * products.cost_price END)) as cost')
            ->groupBy('date')
            ->pluck('cost', 'date');

        $giftCostsByDay = SaleItemGift::query()
            ->join('sale_items', 'sale_items.id', '=', 'sale_item_gifts.sale_item_id')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_item_gifts.product_id')
            ->where('sales.status', '!=', 'cancelled')
            ->whereBetween('sales.created_at', [$from, $to])
            ->selectRaw('DATE(sales.created_at) as date, SUM((CASE WHEN sale_item_gifts.unit_cost IS NOT NULL THEN sale_item_gifts.unit_cost * sale_item_gifts.quantity ELSE COALESCE(sale_item_gifts.base_quantity, sale_item_gifts.quantity) * products.cost_price END)) as cost')
            ->groupBy('date')
            ->pluck('cost', 'date');

        $repairsByDay = Repair::query()
            ->whereIn('status', self::REPAIR_REVENUE_STATUSES)
            ->whereRaw(
                'COALESCE(returned_at, completed_at, updated_at) BETWEEN ? AND ?',
                [$from, $to]
            )
            ->selectRaw('DATE(COALESCE(returned_at, completed_at, updated_at)) as date, SUM(COALESCE(final_cost, estimated_cost, 0)) as revenue')
            ->groupBy('date')
            ->pluck('revenue', 'date');

        $labels = [];
        $revenue = [];
        $profit = [];
        $salesRevenue = [];
        $repairRevenue = [];
        $salesProfit = [];

        for ($cursor = $from->copy(); $cursor->lte($to); $cursor->addDay()) {

            $key = $cursor->format('Y-m-d');

            $posRevenueDay = (float) ($salesByDay[$key] ?? 0);
            $costDay = (float) ($costByDay[$key] ?? 0) + (float) ($giftCostsByDay[$key] ?? 0);
            $repairRevenueDay = (float) ($repairsByDay[$key] ?? 0);

            $labels[] = $cursor->format('d/m');
            $revenue[] = $posRevenueDay + $repairRevenueDay;
            $profit[] = ($posRevenueDay - $costDay) + $repairRevenueDay;
            $salesRevenue[] = $posRevenueDay;
            $repairRevenue[] = $repairRevenueDay;
            $salesProfit[] = $posRevenueDay - $costDay;
        }

        return [
            // Khoảng ngày thật sự của dữ liệu để giao diện biết bộ lọc đang ở khoảng nào.
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'labels' => $labels,
            'revenue' => $revenue,
            'profit' => $profit,
            // Tách theo nguồn để lọc Bán hàng / Sửa chữa ở biểu đồ (lợi nhuận sửa chữa = doanh thu sửa chữa như KPI).
            'salesRevenue' => $salesRevenue,
            'repairRevenue' => $repairRevenue,
            'salesProfit' => $salesProfit,
            'repairProfit' => $repairRevenue,
        ];
    }

    /**
     * Cơ cấu doanh thu: máy/thiết bị (imei) - phụ kiện (normal/combo) - dịch vụ sửa chữa.
     */
    private function revenueBreakdown(Carbon $from, Carbon $to): array
    {
        $itemRevenue = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.status', '!=', 'cancelled')
            ->whereBetween('sales.created_at', [$from, $to])
            ->selectRaw('products.product_type, SUM(sale_items.subtotal) as revenue')
            ->groupBy('products.product_type')
            ->pluck('revenue', 'product_type');

        $deviceRevenue = (float) ($itemRevenue['imei'] ?? 0);

        $accessoryRevenue = (float) (
            ($itemRevenue['normal'] ?? 0)
            + ($itemRevenue['combo'] ?? 0)
            + ($itemRevenue['service'] ?? 0)
        );

        $repairRevenue = (float) Repair::query()
            ->whereIn('status', self::REPAIR_REVENUE_STATUSES)
            ->whereRaw(
                'COALESCE(returned_at, completed_at, updated_at) BETWEEN ? AND ?',
                [$from, $to]
            )
            ->selectRaw('COALESCE(SUM(COALESCE(final_cost, estimated_cost, 0)), 0) as total')
            ->value('total');

        return [
            'labels' => ['Bán máy/Thiết bị', 'Dịch vụ Sửa chữa', 'Bán Phụ kiện'],
            'data' => [$deviceRevenue, $repairRevenue, $accessoryRevenue],
        ];
    }

    /**
     * Giao dịch gần đây: gộp hóa đơn POS + phiếu sửa chữa, sắp theo thời gian.
     */
    private function recentOrders(): array
    {
        $recentSales = Sale::query()
            ->with('customer:id,full_name')
            ->where('status', '!=', 'cancelled')
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn ($sale) => [
                'code' => $sale->code,
                'time' => $sale->created_at->format('H:i d/m'),
                'timestamp' => $sale->created_at->timestamp,
                'customer' => $sale->customer?->full_name ?: 'Khách lẻ',
                'total' => (float) $sale->grand_total,
                'status' => (float) $sale->paid_amount >= (float) $sale->grand_total
                    ? 'paid'
                    : 'debt',
                'status_label' => null,
                'type' => 'pos',
            ]);

        $recentRepairs = Repair::query()
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn ($repair) => [
                'code' => $repair->code,
                'time' => $repair->created_at->format('H:i d/m'),
                'timestamp' => $repair->created_at->timestamp,
                'customer' => $repair->customer_name ?: 'Khách lẻ',
                'total' => (float) ($repair->final_cost ?? $repair->estimated_cost ?? 0),
                'status' => $repair->status === 'returned' ? 'paid' : 'other',
                'status_label' => self::REPAIR_STATUS_LABELS[$repair->status] ?? $repair->status,
                'type' => 'repair',
            ]);

        return $recentSales
            ->concat($recentRepairs)
            ->sortByDesc('timestamp')
            ->take(8)
            ->map(function ($order) {
                unset($order['timestamp']);
                return $order;
            })
            ->values()
            ->all();
    }

    /**
     * Việc cần làm hôm nay (hiển thị cùng các nhắc nhở ở đầu Dashboard).
     * Chỉ trả về mục có việc và chỉ khi người dùng có quyền xem phần đó.
     */
    private function todos(): array
    {
        $user = auth()->user();
        $todos = [];

        if ($user?->can('repairs.view')) {
            $counts = Repair::query()
                ->whereIn('status', ['pending', 'repairing', 'done'])
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');

            $definitions = [
                'pending' => ['Đơn sửa đang chờ sửa', 'phiếu mới tiếp nhận, chưa bắt đầu sửa', 'amber'],
                'repairing' => ['Đơn đang sửa', 'phiếu đang được xử lý', 'blue'],
                'done' => ['Đơn đã sửa xong, chờ trả khách', 'phiếu hoàn tất, cần liên hệ khách đến lấy', 'emerald'],
            ];

            foreach ($definitions as $status => [$title, $hint, $tone]) {
                $count = (int) ($counts[$status] ?? 0);
                if ($count > 0) {
                    $todos[] = [
                        'key' => "repair-{$status}",
                        'title' => $title,
                        'hint' => $hint,
                        'count' => $count,
                        'tone' => $tone,
                        'url' => "/repairs?status={$status}",
                    ];
                }
            }
        }

        return $todos;
    }
}
