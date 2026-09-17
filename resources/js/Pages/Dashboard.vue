<script setup>
import { ref, computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

// Icons
import {
    DollarSign,
    TrendingUp,
    Wallet,
    AlertCircle,
    ArrowUpRight,
    ArrowDownRight,
    ShoppingBag,
    Wrench,
    ChevronRight,
} from 'lucide-vue-next'

// Chart.js Setup
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Title,
    Tooltip,
    Legend,
    ArcElement,
    Filler
} from 'chart.js'
import { Line, Doughnut } from 'vue-chartjs'

ChartJS.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Title,
    Tooltip,
    Legend,
    ArcElement,
    Filler
)

/*
|--------------------------------------------------------------------------
| Props từ DashboardController (dữ liệu thật)
|--------------------------------------------------------------------------
*/
const props = defineProps({
    stats: {
        type: Object,
        default: () => ({
            todayRevenue: 0,
            revenueChangePercent: 0,
            todayProfit: 0,
            cashAmount: 0,
            bankAmount: 0,
            totalDebt: 0,
            debtorCount: 0,
        })
    },
    topDebtors: {
        type: Array,
        default: () => []
    },
    recentOrders: {
        type: Array,
        default: () => []
    },
    revenueChart: {
        type: Object,
        default: () => ({
            week: { labels: [], revenue: [], profit: [] },
            month: { labels: [], revenue: [], profit: [] },
        })
    },
    revenueBreakdown: {
        type: Object,
        default: () => ({
            labels: ['Bán máy/Thiết bị', 'Dịch vụ Sửa chữa', 'Bán Phụ kiện'],
            data: [0, 0, 0],
        })
    },
})

// Formatting helper
const formatMoney = (val) => Number(val || 0).toLocaleString('vi-VN') + ' đ'

// Data cấu hình Biểu đồ Doanh thu (Line Chart)
const filterPeriod = ref('7days')

const activeChart = computed(() => {

    return filterPeriod.value === '7days'
        ? props.revenueChart.week
        : props.revenueChart.month
})

const lineChartData = computed(() => {
    return {
        labels: activeChart.value.labels,
        datasets: [
            {
                label: 'Doanh thu',
                data: activeChart.value.revenue,
                borderColor: '#2563eb',
                backgroundColor: 'rgba(37, 99, 235, 0.1)',
                fill: true,
                tension: 0.35,
                borderWidth: 3,
                pointRadius: 4,
                pointHoverRadius: 6,
            },
            {
                label: 'Lợi nhuận',
                data: activeChart.value.profit,
                borderColor: '#10b981',
                backgroundColor: 'transparent',
                borderDash: [5, 5],
                borderWidth: 2,
                pointRadius: 3,
            }
        ]
    }
})

const lineChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { position: 'top', align: 'end' },
        tooltip: {
            callbacks: {
                label: (ctx) => `${ctx.dataset.label}: ${ctx.raw.toLocaleString('vi-VN')} đ`
            }
        }
    },
    scales: {
        y: {
            ticks: {
                callback: (val) => (val / 1000000) + 'M'
            },
            grid: { color: '#f1f5f9' }
        },
        x: { grid: { display: false } }
    }
}

// Data cấu hình Biểu đồ Cơ cấu Doanh thu (Doughnut Chart)
const doughnutChartData = computed(() => ({
    labels: props.revenueBreakdown.labels,
    datasets: [
        {
            data: props.revenueBreakdown.data,
            backgroundColor: ['#2563eb', '#06b6d4', '#f59e0b'],
            borderWidth: 0,
        }
    ]
}))

const doughnutChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { position: 'bottom' },
        tooltip: {
            callbacks: {
                label: (ctx) => `${ctx.label}: ${Number(ctx.raw || 0).toLocaleString('vi-VN')} đ`
            }
        }
    },
    cutout: '70%'
}
</script>

<template>
    <AdminLayout>
        <div class="space-y-6">

            <!-- 1. HÀNG THỐNG KÊ KPI TÀI CHÍNH -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

                <!-- Card 1: Doanh thu -->
                <div class="p-5 bg-white rounded-2xl border border-slate-200/80 shadow-sm flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Doanh thu hôm nay</span>
                        <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                            <DollarSign :size="20" />
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="text-2xl font-black text-slate-900">
                            {{ formatMoney(stats.todayRevenue) }}
                        </div>
                        <div
                            class="mt-1 flex items-center text-xs font-semibold"
                            :class="stats.revenueChangePercent >= 0 ? 'text-emerald-600' : 'text-rose-600'"
                        >
                            <ArrowUpRight v-if="stats.revenueChangePercent >= 0" :size="15" />
                            <ArrowDownRight v-else :size="15" />
                            <span>{{ stats.revenueChangePercent >= 0 ? '+' : '' }}{{ stats.revenueChangePercent }}% so với hôm qua</span>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Lợi nhuận gộp -->
                <div class="p-5 bg-white rounded-2xl border border-slate-200/80 shadow-sm flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Lợi nhuận gộp (Ước tính)</span>
                        <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                            <TrendingUp :size="20" />
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="text-2xl font-black text-emerald-600">
                            {{ formatMoney(stats.todayProfit) }}
                        </div>
                        <div class="mt-1 text-xs text-slate-400 font-medium">
                            Biên lãi trung bình ~{{ Math.round((stats.todayProfit / (stats.todayRevenue || 1)) * 100) }}%
                        </div>
                    </div>
                </div>

                <!-- Card 3: Phân rã Tiền mặt / CK -->
                <div class="p-5 bg-white rounded-2xl border border-slate-200/80 shadow-sm flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Tiền mặt / Chuyển khoản</span>
                        <div class="w-10 h-10 rounded-xl bg-cyan-50 text-cyan-600 flex items-center justify-center">
                            <Wallet :size="20" />
                        </div>
                    </div>
                    <div class="mt-4 space-y-1">
                        <div class="flex justify-between items-center text-xs">
                            <span class="text-slate-500 font-medium">Tiền mặt:</span>
                            <span class="font-bold text-slate-800">{{ formatMoney(stats.cashAmount) }}</span>
                        </div>
                        <div class="flex justify-between items-center text-xs">
                            <span class="text-slate-500 font-medium">Chuyển khoản:</span>
                            <span class="font-bold text-blue-600">{{ formatMoney(stats.bankAmount) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Card 4: Công nợ -->
                <div class="p-5 bg-white rounded-2xl border border-slate-200/80 shadow-sm flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Tổng công nợ cần thu</span>
                        <div class="w-10 h-10 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center">
                            <AlertCircle :size="20" />
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="text-2xl font-black text-rose-600">
                            {{ formatMoney(stats.totalDebt) }}
                        </div>
                        <div class="mt-1 text-xs text-slate-400 font-medium">
                            {{ stats.debtorCount }} khách hàng đang nợ
                        </div>
                    </div>
                </div>

            </div>

            <!-- 2. KHỐI GIỮA: BIỂU ĐỒ DOANH THU & TOP KHÁCH NỢ -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <!-- Biểu đồ Doanh thu & Lợi nhuận (Chiếm 2 cột) -->
                <div class="lg:col-span-2 bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-4">
                        <div>
                            <h3 class="text-base font-bold text-slate-900">Tăng trưởng Doanh thu & Lợi nhuận</h3>
                            <p class="text-xs text-slate-500">Doanh thu POS + dịch vụ sửa chữa theo thời gian</p>
                        </div>
                        <div class="flex items-center bg-slate-100 p-1 rounded-xl text-xs font-semibold">
                            <button
                                type="button"
                                @click="filterPeriod = '7days'"
                                :class="['px-3 py-1 rounded-lg transition', filterPeriod === '7days' ? 'bg-white shadow text-blue-600 font-bold' : 'text-slate-600']"
                            >
                                7 ngày qua
                            </button>
                            <button
                                type="button"
                                @click="filterPeriod = 'month'"
                                :class="['px-3 py-1 rounded-lg transition', filterPeriod === 'month' ? 'bg-white shadow text-blue-600 font-bold' : 'text-slate-600']"
                            >
                                Tháng này
                            </button>
                        </div>
                    </div>

                    <!-- Line Chart Canvas Container -->
                    <div class="flex-1 min-h-[280px]">
                        <Line
                            v-if="activeChart.labels.length"
                            :data="lineChartData"
                            :options="lineChartOptions"
                        />
                        <div
                            v-else
                            class="h-full flex items-center justify-center text-sm text-slate-400"
                        >
                            Chưa có dữ liệu doanh thu trong khoảng này
                        </div>
                    </div>
                </div>

                <!-- Top Khách nợ nhiều nhất (Chiếm 1 cột) -->
                <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-base font-bold text-slate-900">Top Khách nợ nhiều nhất</h3>
                                <p class="text-xs text-slate-500">Cần ưu tiên nhắc nợ/thu hồi</p>
                            </div>
                            <Link href="/customers" class="text-xs font-bold text-blue-600 hover:underline">
                                Xem tất cả
                            </Link>
                        </div>

                        <div class="space-y-3">
                            <div
                                v-for="(debtor, index) in topDebtors"
                                :key="debtor.id"
                                class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 border border-slate-100 hover:bg-slate-100/80 transition"
                            >
                                <div class="min-w-0 flex-1">
                                    <div class="text-sm font-bold text-slate-900 truncate">
                                        {{ index + 1 }}. {{ debtor.name }}
                                    </div>
                                    <div class="text-xs text-slate-400">
                                        SĐT: {{ debtor.phone || '---' }}
                                    </div>
                                </div>
                                <div class="text-right ml-2 shrink-0">
                                    <div class="text-sm font-black text-rose-600">
                                        {{ formatMoney(debtor.debt) }}
                                    </div>
                                </div>
                            </div>

                            <div
                                v-if="!topDebtors.length"
                                class="py-8 text-center text-sm text-slate-400"
                            >
                                Không có khách hàng nợ
                            </div>
                        </div>
                    </div>

                    <Link
                        href="/customers"
                        class="mt-4 w-full py-2.5 bg-rose-50 text-rose-700 hover:bg-rose-100 text-xs font-bold rounded-xl flex items-center justify-center gap-1 transition"
                    >
                        <span>Quản lý danh sách công nợ</span>
                        <ChevronRight :size="15" />
                    </Link>
                </div>

            </div>

            <!-- 3. KHỐI DƯỚI: ĐƠN HÀNG VỪA GIAO DỊCH & CƠ CẤU DOANH THU -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <!-- Đơn hàng / Dịch vụ vừa phát sinh (2 cột) -->
                <div class="lg:col-span-2 bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="text-base font-bold text-slate-900">Giao dịch mới nhất</h3>
                            <p class="text-xs text-slate-500">Các hóa đơn POS và phiếu sửa chữa gần đây</p>
                        </div>
                        <Link href="/sales" class="text-xs font-bold text-blue-600 hover:underline">
                            Tất cả hóa đơn
                        </Link>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="text-[11px] font-extrabold uppercase text-slate-400 border-b border-slate-100 pb-2">
                                    <th class="py-2">Mã đơn</th>
                                    <th class="py-2">Khách hàng</th>
                                    <th class="py-2">Loại</th>
                                    <th class="py-2 text-right">Tổng tiền</th>
                                    <th class="py-2 text-center">Trạng thái</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-xs">
                                <tr v-for="order in recentOrders" :key="order.type + order.code" class="hover:bg-slate-50/80 transition">
                                    <td class="py-3 font-bold text-blue-600">#{{ order.code }}</td>
                                    <td class="py-3 font-semibold text-slate-800">{{ order.customer }}</td>
                                    <td class="py-3">
                                        <span v-if="order.type === 'pos'" class="inline-flex items-center gap-1 text-slate-600">
                                            <ShoppingBag :size="13" /> POS
                                        </span>
                                        <span v-else class="inline-flex items-center gap-1 text-cyan-600">
                                            <Wrench :size="13" /> Sửa chữa
                                        </span>
                                    </td>
                                    <td class="py-3 text-right font-black text-slate-900">{{ formatMoney(order.total) }}</td>
                                    <td class="py-3 text-center">
                                        <span
                                            v-if="order.status === 'paid'"
                                            class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 font-bold text-[10px]"
                                        >
                                            Đã TT
                                        </span>
                                        <span
                                            v-else-if="order.status === 'debt'"
                                            class="px-2 py-0.5 rounded-full bg-rose-100 text-rose-700 font-bold text-[10px]"
                                        >
                                            Ghi nợ
                                        </span>
                                        <span
                                            v-else
                                            class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 font-bold text-[10px]"
                                        >
                                            {{ order.status_label }}
                                        </span>
                                    </td>
                                </tr>

                                <tr v-if="!recentOrders.length">
                                    <td colspan="5" class="py-10 text-center text-slate-400">
                                        Chưa có giao dịch nào
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Cơ cấu doanh thu theo nguồn (1 cột) -->
                <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col justify-between">
                    <div>
                        <h3 class="text-base font-bold text-slate-900 mb-1">Cơ cấu Doanh thu</h3>
                        <p class="text-xs text-slate-500 mb-4">Tỷ trọng đóng góp theo dịch vụ (tháng này)</p>

                        <div class="h-[200px] relative">
                            <Doughnut :data="doughnutChartData" :options="doughnutChartOptions" />
                        </div>
                    </div>

                    <div class="mt-4 pt-4 border-t border-slate-100 text-center">
                        <Link
                            href="/pos"
                            class="inline-flex items-center justify-center gap-2 w-full py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-md transition"
                        >
                            <ShoppingBag :size="16" />
                            <span>Mở màn hình Bán Hàng POS</span>
                        </Link>
                    </div>
                </div>

            </div>

        </div>
    </AdminLayout>
</template>
