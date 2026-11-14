<script setup>
import { ref, reactive, computed, onMounted, onBeforeUnmount } from 'vue'
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
    ChevronLeft,
    BellRing,
    ChevronDown,
    Check,
    Layers,
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
import { formatCurrency } from '@/utils/format'
import axios from 'axios'
import DateRangeFilter from '@/Components/UI/DateRangeFilter.vue'

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
    expenseReminders: { type: Array, default: () => [] },
    todos: { type: Array, default: () => [] },
})

// Formatting helper
const formatMoney = formatCurrency

/*
|--------------------------------------------------------------------------
| Thông báo nổi bật: nhắc chi phí + việc cần làm, tự chuyển qua lại
|--------------------------------------------------------------------------
*/
const NOTICE_TONES = {
    rose: { bar: 'from-rose-500 to-red-600', soft: 'bg-rose-100 text-rose-700' },
    amber: { bar: 'from-amber-400 to-orange-500', soft: 'bg-amber-100 text-amber-800' },
    blue: { bar: 'from-blue-500 to-indigo-600', soft: 'bg-blue-100 text-blue-700' },
    emerald: { bar: 'from-emerald-500 to-teal-600', soft: 'bg-emerald-100 text-emerald-700' },
}

const notices = computed(() => {
    const expenses = props.expenseReminders.map(item => {
        const overdue = item.days_until < 0
        return {
            key: `expense-${item.id}-${item.period}`,
            kind: 'expense',
            tone: overdue ? 'rose' : 'amber',
            label: overdue ? 'Chi phí quá hạn' : 'Chi phí sắp đến hạn',
            title: item.description,
            detail: `Hạn ${item.due_date} · ${formatMoney(item.amount)}`,
            badge: overdue ? `Quá hạn ${Math.abs(item.days_until)} ngày` : item.days_until === 0 ? 'Đến hạn hôm nay' : `Còn ${item.days_until} ngày`,
            url: '/expenses',
            cta: 'Quản lý lịch chi phí',
            urgent: overdue || item.days_until === 0,
        }
    })
    const todos = props.todos.map(todo => ({
        key: todo.key,
        kind: 'todo',
        tone: todo.tone || 'blue',
        label: 'Việc cần làm',
        title: `${todo.count} ${todo.title.charAt(0).toLowerCase()}${todo.title.slice(1)}`,
        detail: todo.hint,
        badge: `${todo.count} phiếu`,
        url: todo.url,
        cta: 'Xem danh sách',
        urgent: false,
    }))
    // Việc khẩn (quá hạn / đến hạn hôm nay) lên trước, sau đó là việc cần làm, cuối cùng là nhắc sắp đến hạn.
    return [...expenses.filter(item => item.urgent), ...todos, ...expenses.filter(item => !item.urgent)]
})

const noticeIndex = ref(0)
const noticePaused = ref(false)
const currentNotice = computed(() => notices.value[noticeIndex.value % Math.max(notices.value.length, 1)] || null)
const noticeTone = computed(() => NOTICE_TONES[currentNotice.value?.tone] || NOTICE_TONES.amber)
const moveNotice = (step) => {
    const total = notices.value.length
    if (total < 2) return
    noticeIndex.value = (noticeIndex.value + step + total) % total
}
let noticeTimer = null
onMounted(() => {
    noticeTimer = setInterval(() => { if (!noticePaused.value) moveNotice(1) }, 5000)
})
onBeforeUnmount(() => clearInterval(noticeTimer))

/*
|--------------------------------------------------------------------------
| Biểu đồ doanh thu: lọc theo kỳ và theo nguồn thu (bán hàng / sửa chữa)
|--------------------------------------------------------------------------
*/
const filterSource = ref('all')

const SOURCE_OPTIONS = [
    { value: 'all', label: 'Tất cả' },
    { value: 'sales', label: 'Bán hàng' },
    { value: 'repair', label: 'Sửa chữa' },
]
const SOURCE_TEXT = {
    all: { revenue: 'Doanh thu', profit: 'Lợi nhuận', hint: 'Bán hàng + sửa chữa theo ngày' },
    sales: { revenue: 'Doanh thu bán hàng', profit: 'Lợi nhuận bán hàng', hint: 'Chỉ hóa đơn bán hàng POS' },
    repair: { revenue: 'Doanh thu sửa chữa', profit: 'Lợi nhuận sửa chữa', hint: 'Chỉ phiếu sửa đã hoàn tất' },
}

// Menu lọc nguồn thu: thu gọn như bộ lọc danh mục ở POS, bấm ra ngoài hoặc Esc để đóng.
const sourceMenuOpen = ref(false)
const sourceMenuRoot = ref(null)
const pickSource = (value) => { filterSource.value = value; sourceMenuOpen.value = false }
const onSourceOutside = (event) => { if (sourceMenuOpen.value && sourceMenuRoot.value && !sourceMenuRoot.value.contains(event.target)) sourceMenuOpen.value = false }
const onSourceKey = (event) => { if (event.key === 'Escape') sourceMenuOpen.value = false }
onMounted(() => { document.addEventListener('pointerdown', onSourceOutside); document.addEventListener('keydown', onSourceKey) })
onBeforeUnmount(() => { document.removeEventListener('pointerdown', onSourceOutside); document.removeEventListener('keydown', onSourceKey) })

// Khoảng ngày của biểu đồ: mặc định 7 ngày gần nhất. "7 ngày qua" và "Tháng này" đã có sẵn dữ liệu từ server,
// các khoảng khác được nạp ngầm ngay khi người dùng chọn xong ngày bắt đầu - kết thúc (không cần bấm Áp dụng).
const EMPTY_CHART = { labels: [], revenue: [], profit: [] }
const rangeKey = (range) => `${range?.from || ''}|${range?.to || ''}`
const weekKey = computed(() => rangeKey(props.revenueChart.week))
const monthKey = computed(() => rangeKey(props.revenueChart.month))
const chartRange = reactive({ from: props.revenueChart.week?.from || '', to: props.revenueChart.week?.to || '' })
const fetchedChart = ref(null)
const chartLoading = ref(false)
const chartError = ref('')

const activeChart = computed(() => {
    const key = rangeKey(chartRange)
    if (key === weekKey.value) return props.revenueChart.week
    if (key === monthKey.value) return props.revenueChart.month
    if (fetchedChart.value && rangeKey(fetchedChart.value) === key) return fetchedChart.value

    return EMPTY_CHART
})

let chartRequest = 0
const loadChartRange = async ({ from, to }) => {
    const id = ++chartRequest
    chartLoading.value = true
    chartError.value = ''
    try {
        const { data } = await axios.get(route('dashboard.chart'), { params: { from, to } })
        if (id !== chartRequest) return
        fetchedChart.value = data
        // Server có thể thu hẹp khoảng quá dài: đồng bộ lại để nhãn bộ lọc đúng với dữ liệu đang xem.
        chartRange.from = data.from
        chartRange.to = data.to
    } catch (error) {
        if (id !== chartRequest) return
        chartError.value = error?.response?.data?.errors?.to?.[0] || error?.response?.data?.message || 'Không tải được biểu đồ cho khoảng ngày này.'
    } finally {
        if (id === chartRequest) chartLoading.value = false
    }
}

const onChartRangeChange = ({ from, to }) => {
    if (!from || !to) return
    chartError.value = ''
    const key = rangeKey({ from, to })
    if (key === weekKey.value || key === monthKey.value) { chartRequest++; chartLoading.value = false; return }
    loadChartRange({ from, to })
}

const seriesFor = (key) => {
    const chart = activeChart.value
    const map = {
        all: { revenue: chart.revenue, profit: chart.profit },
        sales: { revenue: chart.salesRevenue ?? chart.revenue, profit: chart.salesProfit ?? chart.profit },
        repair: { revenue: chart.repairRevenue ?? [], profit: chart.repairProfit ?? chart.repairRevenue ?? [] },
    }
    return map[filterSource.value][key] || []
}
// Rút gọn số tiền trên trục: 1,5 tr · 250 k · 2 tỷ (tránh số lẻ kiểu 6e-7M).
const trimNumber = (value) => String(Math.round(value * 10) / 10).replace('.', ',')
const compactMoney = (value) => {
    const amount = Number(value) || 0
    const abs = Math.abs(amount)
    const sign = amount < 0 ? '-' : ''
    if (abs >= 1e9) return `${sign}${trimNumber(abs / 1e9)} tỷ`
    if (abs >= 1e6) return `${sign}${trimNumber(abs / 1e6)} tr`
    if (abs >= 1e3) return `${sign}${trimNumber(abs / 1e3)} k`
    return `${sign}${Math.round(abs)}`
}

const sumSeries = (key) => seriesFor(key).reduce((total, value) => total + (Number(value) || 0), 0)
const totalRevenue = computed(() => sumSeries('revenue'))
const totalProfit = computed(() => sumSeries('profit'))

const lineChartData = computed(() => {
    const text = SOURCE_TEXT[filterSource.value]
    return {
        labels: activeChart.value.labels,
        datasets: [
            {
                label: text.revenue,
                data: seriesFor('revenue'),
                borderColor: '#2563eb',
                backgroundColor: 'rgba(37, 99, 235, 0.1)',
                fill: true,
                tension: 0.35,
                borderWidth: 3,
                pointRadius: activeChart.value.labels.length > 31 ? 0 : 4,
                pointHoverRadius: 6,
            },
            {
                label: text.profit,
                data: seriesFor('profit'),
                borderColor: '#10b981',
                backgroundColor: 'transparent',
                borderDash: [5, 5],
                borderWidth: 2,
                pointRadius: activeChart.value.labels.length > 31 ? 0 : 3,
            }
        ]
    }
})

const lineChartOptions = computed(() => {
    const allValues = [...seriesFor('revenue'), ...seriesFor('profit')].map(Number)
    const hasData = allValues.some(value => value !== 0)
    return {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { position: 'top', align: 'end', labels: { usePointStyle: true, boxWidth: 8, boxHeight: 8 } },
            tooltip: {
                callbacks: {
                    label: (ctx) => `${ctx.dataset.label}: ${formatCurrency(ctx.raw)}`
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                // Chưa có số liệu: cố định thang 0 → 1 triệu để trục không hiện số lẻ.
                suggestedMax: hasData ? undefined : 1000000,
                ticks: {
                    maxTicksLimit: 6,
                    callback: (val) => compactMoney(val)
                },
                grid: { color: '#f1f5f9' }
            },
            x: { grid: { display: false } }
        }
    }
})

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
                label: (ctx) => `${ctx.label}: ${formatCurrency(ctx.raw)}`
            }
        }
    },
    cutout: '70%'
}
</script>

<template>
    <AdminLayout>
        <div class="space-y-3">

            <!-- Thông báo nổi bật: tự chuyển qua từng mục, dừng khi rê chuột vào -->
            <section
                v-if="currentNotice"
                class="overflow-hidden rounded-2xl bg-gradient-to-r text-white shadow-lg transition-colors duration-500"
                :class="noticeTone.bar"
                @mouseenter="noticePaused = true"
                @mouseleave="noticePaused = false"
                @focusin="noticePaused = true"
                @focusout="noticePaused = false"
            >
                <Transition name="notice" mode="out-in">
                    <div :key="currentNotice.key" class="px-4 py-3 sm:flex sm:items-center sm:gap-3">
                        <div class="flex min-w-0 items-start gap-3 sm:flex-1 sm:items-center">
                            <span class="relative mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white/20 sm:mt-0 sm:h-11 sm:w-11">
                                <span class="absolute inset-0 animate-ping rounded-full bg-white/25"></span>
                                <Wrench v-if="currentNotice.kind === 'todo'" :size="22" class="relative" />
                                <AlertCircle v-else-if="currentNotice.urgent" :size="22" class="relative notice-shake" />
                                <BellRing v-else :size="22" class="relative notice-shake" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="text-[11px] font-bold uppercase tracking-wider text-white/80">{{ currentNotice.label }}</div>
                                <div class="notice-clamp text-[15px] font-extrabold leading-snug sm:truncate sm:text-lg sm:leading-tight">{{ currentNotice.title }}</div>
                                <div class="notice-clamp mt-0.5 text-sm leading-snug text-white/90 sm:truncate">{{ currentNotice.detail }}</div>
                            </div>
                        </div>
                        <div class="mt-3 flex items-center justify-between gap-2 sm:mt-0 sm:shrink-0 sm:justify-end sm:gap-3">
                            <span class="shrink-0 rounded-full bg-white px-3 py-1 text-xs font-extrabold" :class="noticeTone.soft">{{ currentNotice.badge }}</span>
                            <Link :href="currentNotice.url" class="inline-flex shrink-0 items-center gap-1 rounded-xl bg-white/95 px-3 py-1.5 text-sm font-bold text-slate-800 shadow-sm transition hover:bg-white">
                                {{ currentNotice.cta }}<ChevronRight :size="16" />
                            </Link>
                        </div>
                    </div>
                </Transition>
                <div v-if="notices.length > 1" class="flex items-center justify-between gap-2 bg-black/10 px-3 py-1">
                    <button type="button" class="rounded-md p-0.5 text-white/80 hover:bg-white/15 hover:text-white" aria-label="Thông báo trước" @click="moveNotice(-1)"><ChevronLeft :size="16" /></button>
                    <div class="flex items-center gap-1.5">
                        <button
                            v-for="(notice, index) in notices"
                            :key="notice.key"
                            type="button"
                            class="hidden h-1.5 rounded-full transition-all sm:block"
                            :class="index === noticeIndex % notices.length ? 'w-5 bg-white' : 'w-1.5 bg-white/50 hover:bg-white/80'"
                            :aria-label="`Xem thông báo ${index + 1}`"
                            @click="noticeIndex = index"
                        ></button>
                        <span class="ml-1 text-[11px] font-semibold text-white/80">{{ (noticeIndex % notices.length) + 1 }}/{{ notices.length }}</span>
                    </div>
                    <button type="button" class="rounded-md p-0.5 text-white/80 hover:bg-white/15 hover:text-white" aria-label="Thông báo sau" @click="moveNotice(1)"><ChevronRight :size="16" /></button>
                </div>
            </section>

            <!-- 1. HÀNG THỐNG KÊ KPI TÀI CHÍNH -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">

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
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-3">

                <!-- Biểu đồ Doanh thu & Lợi nhuận (Chiếm 2 cột) -->
                <div class="lg:col-span-2 bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col">
                    <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <h3 class="text-base font-bold text-slate-900">Doanh thu & Lợi nhuận</h3>
                            <p class="text-xs text-slate-500">{{ SOURCE_TEXT[filterSource].hint }}</p>
                        </div>
                        <!-- Lọc nguồn thu và chọn ngày cùng một hàng (tự xuống dòng nếu không đủ chỗ) -->
                        <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                            <div ref="sourceMenuRoot" class="relative">
                                <button
                                    type="button"
                                    class="flex h-9 items-center gap-1.5 rounded-lg border px-2.5 text-xs font-semibold transition-colors"
                                    :class="filterSource !== 'all' ? 'border-blue-600 bg-blue-50 text-blue-700' : 'border-slate-200 bg-white text-slate-600 hover:border-blue-200 hover:bg-blue-50'"
                                    aria-haspopup="listbox"
                                    :aria-expanded="sourceMenuOpen"
                                    title="Lọc theo nguồn thu"
                                    @click="sourceMenuOpen = !sourceMenuOpen"
                                >
                                    <Layers class="h-3.5 w-3.5 shrink-0" />
                                    <span>{{ SOURCE_OPTIONS.find(option => option.value === filterSource)?.label }}</span>
                                    <ChevronDown class="h-3.5 w-3.5 shrink-0 transition-transform" :class="sourceMenuOpen ? 'rotate-180' : ''" />
                                </button>
                                <div v-if="sourceMenuOpen" role="listbox" class="absolute left-0 top-full z-30 mt-1 w-40 overflow-hidden rounded-lg border border-slate-200 bg-white py-1 shadow-lg sm:left-auto sm:right-0">
                                    <button
                                        v-for="option in SOURCE_OPTIONS"
                                        :key="option.value"
                                        type="button"
                                        role="option"
                                        class="flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-xs hover:bg-blue-50"
                                        :class="filterSource === option.value ? 'font-bold text-blue-700' : 'text-slate-700'"
                                        @click="pickSource(option.value)"
                                    >
                                        <span>{{ option.label }}</span>
                                        <Check v-if="filterSource === option.value" class="h-3.5 w-3.5 shrink-0" />
                                    </button>
                                </div>
                            </div>
                            <DateRangeFilter
                                class="!w-auto min-w-[180px] flex-1 sm:flex-none"
                                v-model:from="chartRange.from"
                                v-model:to="chartRange.to"
                                :allow-all="false"
                                compact
                                @change="onChartRangeChange"
                            />
                        </div>
                    </div>

                    <!-- Tổng doanh thu / lợi nhuận của khoảng đang xem -->
                    <div class="mb-3 grid grid-cols-2 gap-3">
                        <div class="rounded-xl bg-blue-50 px-4 py-2.5">
                            <div class="text-xs font-semibold text-blue-700">{{ SOURCE_TEXT[filterSource].revenue }}</div>
                            <div class="text-lg font-black text-blue-950">{{ formatMoney(totalRevenue) }}</div>
                        </div>
                        <div class="rounded-xl bg-emerald-50 px-4 py-2.5">
                            <div class="text-xs font-semibold text-emerald-700">{{ SOURCE_TEXT[filterSource].profit }}</div>
                            <div class="text-lg font-black text-emerald-950">{{ formatMoney(totalProfit) }}</div>
                        </div>
                    </div>
                    <p v-if="chartError" class="mb-2 rounded-lg bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700" role="alert">{{ chartError }}</p>

                    <!-- Line Chart Canvas Container -->
                    <div class="flex-1 min-h-[280px]">
                        <Line
                            v-if="activeChart.labels.length && !chartLoading"
                            :data="lineChartData"
                            :options="lineChartOptions"
                        />
                        <div
                            v-else
                            class="h-full flex items-center justify-center text-sm text-slate-400"
                        >
                            {{ chartLoading ? 'Đang tải biểu đồ…' : 'Chưa có dữ liệu doanh thu trong khoảng này' }}
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
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-3">

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
                        <table class="app-data-table w-full text-left border-collapse">
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

<style scoped>
.notice-clamp { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
@media (min-width: 640px) { .notice-clamp { display: block; -webkit-line-clamp: unset; } }
.notice-enter-active,
.notice-leave-active { transition: opacity 0.25s ease, transform 0.25s ease; }
.notice-enter-from { opacity: 0; transform: translateX(16px); }
.notice-leave-to { opacity: 0; transform: translateX(-16px); }
.notice-shake { animation: notice-shake 2.4s ease-in-out infinite; transform-origin: 50% 0; }
@keyframes notice-shake {
    0%, 70%, 100% { transform: rotate(0); }
    75% { transform: rotate(14deg); }
    80% { transform: rotate(-12deg); }
    85% { transform: rotate(8deg); }
    90% { transform: rotate(-6deg); }
}
@media (prefers-reduced-motion: reduce) {
    .notice-shake { animation: none; }
}
</style>
