<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Repair;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Nhãn trạng thái phiếu sửa chữa (dùng để hiển thị ở bảng giao dịch gần đây).
     */
    private const REPAIR_STATUS_LABELS = [

        'pending' => 'Mới nhận',
        'checking' => 'Đang kiểm tra',
        'waiting_parts' => 'Chờ linh kiện',
        'repairing' => 'Đang sửa',
        'done' => 'Đã sửa xong',
        'returned' => 'Đã trả khách',
        'cancelled' => 'Đã hủy',
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
            ->where('payment_method', 'cash')
            ->sum('paid_amount');

        $bankAmount = (float) (clone $salesToday)
            ->where('payment_method', '!=', 'cash')
            ->sum('paid_amount');

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
                COALESCE(SUM(sale_items.quantity * products.cost_price), 0) as cost
            ')
            ->first();

        $posProfit = (float) $posRow->revenue - (float) $posRow->cost;

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
            ->selectRaw('DATE(sales.created_at) as date, SUM(sale_items.quantity * products.cost_price) as cost')
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

        for ($cursor = $from->copy(); $cursor->lte($to); $cursor->addDay()) {

            $key = $cursor->format('Y-m-d');

            $posRevenueDay = (float) ($salesByDay[$key] ?? 0);
            $costDay = (float) ($costByDay[$key] ?? 0);
            $repairRevenueDay = (float) ($repairsByDay[$key] ?? 0);

            $labels[] = $cursor->format('d/m');
            $revenue[] = $posRevenueDay + $repairRevenueDay;
            $profit[] = ($posRevenueDay - $costDay) + $repairRevenueDay;
        }

        return [
            'labels' => $labels,
            'revenue' => $revenue,
            'profit' => $profit,
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
}
