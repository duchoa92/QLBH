<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import { ref, reactive, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import axios from 'axios'
import { formatMoney, formatDate } from '@/utils/format'
import SimpleBarChart from '@/Components/Charts/SimpleBarChart.vue'
import BarcodeCameraButton from '@/Components/BarcodeCameraButton.vue'
import ListToolbar from '@/Components/UI/ListToolbar.vue'
import ListFilterSelect from '@/Components/UI/ListFilterSelect.vue'
import DateRangeFilter from '@/Components/UI/DateRangeFilter.vue'
import HighlightText from '@/Components/UI/HighlightText.vue'
import {
    TrendingUp,
    PiggyBank,
    Flame,
    Boxes,
    Wallet,
    Loader2,
} from 'lucide-vue-next'

const props = defineProps({
    categories: { type: Array, default: () => [] },
    brands: { type: Array, default: () => [] },
    staff: { type: Array, default: () => [] },
})

// Tùy chọn cho các ô lọc dùng chung giữa các tab
const withAll = (label, items) => [{ value: '', label }, ...items]
const categoryOptions = computed(() => withAll('Tất cả danh mục', props.categories.map(item => ({ value: item.id, label: item.name }))))
const brandOptions = computed(() => withAll('Tất cả thương hiệu', props.brands.map(item => ({ value: item.id, label: item.name }))))
const staffOptions = computed(() => withAll('Tất cả nhân viên', props.staff.map(item => ({ value: item.id, label: item.name }))))
const paymentOptions = withAll('Mọi thanh toán', [
    { value: 'cash', label: 'Tiền mặt' },
    { value: 'bank', label: 'Chuyển khoản' },
    { value: 'card', label: 'Thẻ' },
    { value: 'mixed', label: 'Kết hợp' },
])
const sourceOptions = withAll('Tất cả nguồn thu', [
    { value: 'sales', label: 'Bán hàng' },
    { value: 'repair', label: 'Sửa chữa' },
])
const stockStatusOptions = withAll('Mọi tình trạng tồn', [
    { value: 'in', label: 'Còn hàng' },
    { value: 'low', label: 'Sắp hết hàng' },
    { value: 'out', label: 'Hết hàng' },
])
const debtLevelOptions = withAll('Mọi mức nợ', [
    { value: 'lt1m', label: 'Dưới 1 triệu' },
    { value: '1m_5m', label: '1 – 5 triệu' },
    { value: '5m_20m', label: '5 – 20 triệu' },
    { value: 'gt20m', label: 'Trên 20 triệu' },
])

/*
|--------------------------------------------------------------------------
| Tabs
|--------------------------------------------------------------------------
*/

const tabs = [
    { key: 'revenue', label: 'Doanh thu', icon: TrendingUp },
    { key: 'profit', label: 'Lợi nhuận', icon: PiggyBank },
    { key: 'best-sellers', label: 'Bán chạy', icon: Flame },
    { key: 'inventory', label: 'Hàng tồn', icon: Boxes },
    { key: 'debts', label: 'Công nợ', icon: Wallet },
]

const validTabKeys = tabs.map(tab => tab.key)
const readActiveTab = () => {
    const requested = new URL(window.location.href).searchParams.get('tab')
    return validTabKeys.includes(requested) ? requested : 'revenue'
}
const activeTab = ref(readActiveTab())
const loading = ref(false)

const selectTab = (tab) => {
    if (!validTabKeys.includes(tab) || activeTab.value === tab) return
    activeTab.value = tab
    const url = new URL(window.location.href)
    url.searchParams.set('tab', tab)
    router.push({ url: `${url.pathname}${url.search}${url.hash}`, preserveState: true, preserveScroll: true })
}

const restoreTabFromUrl = () => { activeTab.value = readActiveTab() }
window.addEventListener('popstate', restoreTabFromUrl)
onBeforeUnmount(() => window.removeEventListener('popstate', restoreTabFromUrl))

/*
|--------------------------------------------------------------------------
| Bộ lọc ngày (dùng cho: Doanh thu, Lợi nhuận, Bán chạy)
|--------------------------------------------------------------------------
*/

const today = new Date()
const firstDayOfMonth = new Date(today.getFullYear(), today.getMonth(), 1)

// Dùng ngày theo giờ máy (không dùng toISOString vì quy về UTC làm lệch 1 ngày ở múi giờ +7,
// ví dụ 01/11 00:00 giờ Việt Nam sẽ thành 31/10).
const toInputDate = (date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`

const filters = reactive({
    from: toInputDate(firstDayOfMonth),
    to: toInputDate(today),
})

/*
|--------------------------------------------------------------------------
| Doanh thu
|--------------------------------------------------------------------------
*/

const revenue = reactive({
    summary: null,
    daily: [],
    by_payment_method: [],
})

const revenueFilters = reactive({ source: '', payment_method: '', user_id: '' })

const loadRevenue = async () => {
    loading.value = true

    try {
        const { data } = await axios.get(route('reports.revenue'), {
            params: {
                from: filters.from,
                to: filters.to,
                source: revenueFilters.source || undefined,
                payment_method: revenueFilters.payment_method || undefined,
                user_id: revenueFilters.user_id || undefined,
            },
        })

        revenue.summary = data.summary
        revenue.daily = data.daily
        revenue.by_payment_method = data.by_payment_method
    } finally {
        loading.value = false
    }
}

const revenueChartSeries = computed(() =>
    revenue.daily.map(row => ({
        label: formatDate(row.date).slice(0, 5),
        value: Number(row.revenue),
    }))
)

const paymentMethodLabel = (method) => ({
    cash: 'Tiền mặt',
    card: 'Thẻ',
    transfer: 'Chuyển khoản',
    bank_transfer: 'Chuyển khoản',
    bank: 'Chuyển khoản',
    mixed: 'Kết hợp',
    momo: 'Momo',
    vnpay: 'VNPay',
}[method] ?? method)

/*
|--------------------------------------------------------------------------
| Lợi nhuận
|--------------------------------------------------------------------------
*/

const profit = reactive({
    summary: null,
    daily: [],
    by_product: [],
})

const profitFilters = reactive({ search: '', category_id: '', user_id: '' })

const loadProfit = async () => {
    loading.value = true

    try {
        const { data } = await axios.get(route('reports.profit'), {
            params: {
                from: filters.from,
                to: filters.to,
                search: profitFilters.search.trim() || undefined,
                category_id: profitFilters.category_id || undefined,
                user_id: profitFilters.user_id || undefined,
            },
        })

        profit.summary = data.summary
        profit.daily = data.daily
        profit.by_product = data.by_product
    } finally {
        loading.value = false
    }
}

const profitChartSeries = computed(() =>
    profit.daily.map(row => ({
        label: formatDate(row.date).slice(0, 5),
        value: Number(row.profit),
    }))
)

/*
|--------------------------------------------------------------------------
| Bán chạy
|--------------------------------------------------------------------------
*/

const bestSellers = reactive({
    items: [],
})

const bestSellerOrder = ref('best') // best | worst
const bestSellerOrderOptions = [
    { value: 'best', label: 'Bán chạy nhất' },
    { value: 'worst', label: 'Bán chậm nhất' },
]
const bestSellerLimitOptions = [
    { value: 10, label: 'Top 10' },
    { value: 20, label: 'Top 20' },
    { value: 50, label: 'Top 50' },
]
const bestSellerLimit = ref(10)
const bestSellerFilters = reactive({ search: '', category_id: '', brand_id: '' })

const loadBestSellers = async () => {
    loading.value = true

    try {
        const { data } = await axios.get(route('reports.bestSellers'), {
            params: {
                from: filters.from,
                to: filters.to,
                order: bestSellerOrder.value,
                limit: bestSellerLimit.value,
                search: bestSellerFilters.search.trim() || undefined,
                category_id: bestSellerFilters.category_id || undefined,
                brand_id: bestSellerFilters.brand_id || undefined,
            },
        })

        bestSellers.items = data.items
    } finally {
        loading.value = false
    }
}

/*
|--------------------------------------------------------------------------
| Hàng tồn kho
|--------------------------------------------------------------------------
*/

const inventory = reactive({
    products: null,
    summary: null,
})

const inventoryFilters = reactive({
    search: '',
    category_id: '',
    brand_id: '',
    stock_status: '',
    page: 1,
})

const loadInventory = async (page = 1) => {
    loading.value = true

    try {
        const { data } = await axios.get(route('reports.inventory'), {
            params: {
                search: inventoryFilters.search.trim() || undefined,
                category_id: inventoryFilters.category_id || undefined,
                brand_id: inventoryFilters.brand_id || undefined,
                stock_status: inventoryFilters.stock_status || undefined,
                page,
            },
        })

        inventory.products = data.products
        inventory.summary = data.summary
    } finally {
        loading.value = false
    }
}

/*
|--------------------------------------------------------------------------
| Công nợ
|--------------------------------------------------------------------------
*/

const debts = reactive({
    items: null,
    summary: null,
})

const debtFilters = reactive({
    type: 'customer', // customer | supplier
    search: '',
    level: '',
})

const loadDebts = async (page = 1) => {
    loading.value = true

    try {
        const { data } = await axios.get(route('reports.debts'), {
            params: {
                type: debtFilters.type,
                search: debtFilters.search.trim() || undefined,
                level: debtFilters.level || undefined,
                page,
            },
        })

        debts.items = data.items
        debts.summary = data.summary
    } finally {
        loading.value = false
    }
}

/*
|--------------------------------------------------------------------------
| Điều phối load theo tab
|--------------------------------------------------------------------------
*/

const loadCurrentTab = () => {
    if (activeTab.value === 'revenue') return loadRevenue()
    if (activeTab.value === 'profit') return loadProfit()
    if (activeTab.value === 'best-sellers') return loadBestSellers()
    if (activeTab.value === 'inventory') return loadInventory()
    if (activeTab.value === 'debts') return loadDebts()
}

watch(activeTab, () => {
    loadCurrentTab()
})

watch(
    () => [filters.from, filters.to],
    () => {
        if (['revenue', 'profit', 'best-sellers'].includes(activeTab.value)) {
            loadCurrentTab()
        }
    }
)

watch([bestSellerOrder, bestSellerLimit], () => {
    if (activeTab.value === 'best-sellers') {
        loadBestSellers()
    }
})

watch(() => debtFilters.type, () => {
    if (activeTab.value === 'debts') {
        loadDebts()
    }
})

let searchDebounce = null

watch(() => inventoryFilters.search, () => {
    clearTimeout(searchDebounce)
    searchDebounce = setTimeout(() => {
        if (activeTab.value === 'inventory') loadInventory()
    }, 350)
})

watch(
    () => [inventoryFilters.category_id, inventoryFilters.brand_id, inventoryFilters.stock_status],
    () => {
        if (activeTab.value === 'inventory') loadInventory()
    }
)

watch(() => debtFilters.level, () => {
    if (activeTab.value === 'debts') loadDebts()
})

watch(() => [revenueFilters.source, revenueFilters.payment_method, revenueFilters.user_id], () => {
    if (activeTab.value === 'revenue') loadRevenue()
})

watch(() => [profitFilters.category_id, profitFilters.user_id], () => {
    if (activeTab.value === 'profit') loadProfit()
})

let profitSearchDebounce = null

watch(() => profitFilters.search, () => {
    clearTimeout(profitSearchDebounce)
    profitSearchDebounce = setTimeout(() => {
        if (activeTab.value === 'profit') loadProfit()
    }, 350)
})

watch(() => [bestSellerFilters.category_id, bestSellerFilters.brand_id], () => {
    if (activeTab.value === 'best-sellers') loadBestSellers()
})

let bestSellerSearchDebounce = null

watch(() => bestSellerFilters.search, () => {
    clearTimeout(bestSellerSearchDebounce)
    bestSellerSearchDebounce = setTimeout(() => {
        if (activeTab.value === 'best-sellers') loadBestSellers()
    }, 350)
})

let debtSearchDebounce = null

watch(() => debtFilters.search, () => {
    clearTimeout(debtSearchDebounce)
    debtSearchDebounce = setTimeout(() => {
        if (activeTab.value === 'debts') loadDebts()
    }, 350)
})

onMounted(() => {
    loadCurrentTab()
})
</script>

<template>
    <Head title="Báo cáo & Thống kê" />

    <AuthenticatedLayout>

        <div class="space-y-3">

            <!-- TABS -->
            <section class="flex flex-wrap gap-2 rounded-lg border border-slate-200 bg-white p-2 shadow-sm">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    type="button"
                    class="flex items-center gap-2 rounded-md px-4 py-2 text-sm font-bold transition"
                    :class="activeTab === tab.key
                        ? 'bg-blue-600 text-white shadow-sm'
                        : 'text-slate-600 hover:bg-slate-100'"
                    @click="selectTab(tab.key)"
                >
                    <component :is="tab.icon" :size="16" />
                    {{ tab.label }}
                </button>

                <div
                    v-if="loading"
                    class="ml-auto flex items-center gap-2 px-3 text-xs font-semibold text-slate-400"
                >
                    <Loader2 :size="14" class="animate-spin" />
                    Đang tải...
                </div>
            </section>

            <!-- BỘ LỌC: mỗi tab chỉ có MỘT thanh lọc; ngày + các ô lọc nằm chung, đổi là tự lọc -->
            <section
                v-if="activeTab === 'revenue'"
                class="flex w-full flex-col items-stretch gap-2.5 rounded-2xl border border-slate-200/80 bg-white p-3.5 shadow-sm sm:flex-row sm:flex-wrap sm:items-center sm:p-4"
            >
                <DateRangeFilter v-model:from="filters.from" v-model:to="filters.to" :allow-all="false" align="left" />
                <ListFilterSelect v-model="revenueFilters.source" label="Nguồn thu" :show-placeholder="false" :options="sourceOptions" />
                <ListFilterSelect v-model="revenueFilters.payment_method" label="Thanh toán" :show-placeholder="false" :options="paymentOptions" />
                <ListFilterSelect v-model="revenueFilters.user_id" label="Nhân viên" :show-placeholder="false" :options="staffOptions" />
            </section>

            <!-- ================================================= -->
            <!-- TAB: DOANH THU -->
            <!-- ================================================= -->
            <template v-if="activeTab === 'revenue'">

                <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="text-xs font-bold uppercase tracking-wide text-slate-500">Tổng doanh thu</div>
                        <div class="mt-2 text-2xl font-black text-blue-600">
                            {{ formatMoney(revenue.summary?.total_revenue) }}
                        </div>
                        <div v-if="revenueFilters.source === ''" class="mt-1 flex flex-wrap gap-x-3 text-xs text-slate-500">
                            <span>Bán hàng <strong class="text-slate-700">{{ formatMoney(revenue.summary?.sales_revenue) }}</strong></span>
                            <span>Sửa chữa <strong class="text-slate-700">{{ formatMoney(revenue.summary?.repair_revenue) }}</strong></span>
                        </div>
                    </div>

                    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="text-xs font-bold uppercase tracking-wide text-slate-500">Số đơn / phiếu</div>
                        <div class="mt-2 text-2xl font-black text-slate-950">
                            {{ revenue.summary?.total_orders ?? 0 }}
                        </div>
                    </div>

                    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="text-xs font-bold uppercase tracking-wide text-slate-500">Giá trị TB / đơn</div>
                        <div class="mt-2 text-2xl font-black text-slate-950">
                            {{ formatMoney(revenue.summary?.avg_order_value) }}
                        </div>
                    </div>

                    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="text-xs font-bold uppercase tracking-wide text-slate-500">Tổng chiết khấu</div>
                        <div class="mt-2 text-2xl font-black text-rose-600">
                            {{ formatMoney(revenue.summary?.total_discount) }}
                        </div>
                    </div>
                </section>

                <section class="grid gap-4 xl:grid-cols-3">
                    <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm xl:col-span-2">
                        <h3 class="mb-3 text-sm font-black uppercase text-slate-700">
                            Doanh thu theo ngày
                        </h3>
                        <SimpleBarChart :series="revenueChartSeries" />
                    </div>

                    <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 class="mb-3 text-sm font-black uppercase text-slate-700">
                            Theo phương thức thanh toán
                        </h3>

                        <div class="space-y-3">
                            <div
                                v-for="row in revenue.by_payment_method"
                                :key="row.payment_method"
                                class="flex items-center justify-between rounded-md border border-slate-100 px-3 py-2"
                            >
                                <div>
                                    <div class="text-sm font-bold text-slate-800">
                                        {{ paymentMethodLabel(row.payment_method) }}
                                    </div>
                                    <div class="text-xs text-slate-500">
                                        {{ row.orders }} đơn
                                    </div>
                                </div>

                                <div class="text-sm font-black text-blue-600">
                                    {{ formatMoney(row.revenue) }}
                                </div>
                            </div>

                            <div
                                v-if="!revenue.by_payment_method.length"
                                class="py-6 text-center text-sm text-slate-400"
                            >
                                Chưa có dữ liệu
                            </div>
                        </div>
                    </div>
                </section>

            </template>

            <!-- ================================================= -->
            <!-- TAB: LỢI NHUẬN -->
            <!-- ================================================= -->
            <template v-if="activeTab === 'profit'">

                <ListToolbar v-model="profitFilters.search" placeholder="Tìm sản phẩm theo tên...">
                    <template #filters>
                        <DateRangeFilter v-model:from="filters.from" v-model:to="filters.to" :allow-all="false" align="left" />
                        <ListFilterSelect v-model="profitFilters.category_id" label="Danh mục" :show-placeholder="false" :options="categoryOptions" />
                        <ListFilterSelect v-model="profitFilters.user_id" label="Nhân viên" :show-placeholder="false" :options="staffOptions" />
                    </template>
                </ListToolbar>

                <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="text-xs font-bold uppercase tracking-wide text-slate-500">Doanh thu</div>
                        <div class="mt-2 text-2xl font-black text-slate-950">
                            {{ formatMoney(profit.summary?.revenue) }}
                        </div>
                    </div>

                    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="text-xs font-bold uppercase tracking-wide text-slate-500">Giá vốn hàng bán</div>
                        <div class="mt-2 text-2xl font-black text-rose-600">
                            {{ formatMoney(profit.summary?.cost) }}
                        </div>
                    </div>

                    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="text-xs font-bold uppercase tracking-wide text-slate-500">Lợi nhuận gộp</div>
                        <div
                            class="mt-2 text-2xl font-black"
                            :class="(profit.summary?.profit ?? 0) >= 0 ? 'text-emerald-600' : 'text-rose-600'"
                        >
                            {{ formatMoney(profit.summary?.profit) }}
                        </div>
                    </div>

                    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="text-xs font-bold uppercase tracking-wide text-slate-500">Biên lợi nhuận</div>
                        <div class="mt-2 text-2xl font-black text-slate-950">
                            {{ profit.summary?.margin_percent ?? 0 }}%
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="mb-3 text-sm font-black uppercase text-slate-700">
                        Lợi nhuận theo ngày
                    </h3>
                    <SimpleBarChart :series="profitChartSeries" color="#059669" />
                </section>

                <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <h3 class="border-b border-slate-100 p-5 text-sm font-black uppercase text-slate-700">
                        Lợi nhuận theo sản phẩm (top 15)
                    </h3>

                    <div class="overflow-x-auto"><table class="app-data-table w-full min-w-[640px] border-collapse text-left text-xs">
                        <thead>
                            <tr class="select-none border-b border-slate-200 bg-slate-50/80 text-[11px] font-extrabold uppercase tracking-wider text-slate-500">
                                <th class="px-3.5 py-2.5 text-left">Sản phẩm</th>
                                <th class="px-3.5 py-2.5 text-right">SL bán</th>
                                <th class="px-3.5 py-2.5 text-right">Doanh thu</th>
                                <th class="px-3.5 py-2.5 text-right">Giá vốn</th>
                                <th class="px-3.5 py-2.5 text-right">Lợi nhuận</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs font-medium">
                            <tr class="transition-colors hover:bg-emerald-50/70" v-for="row in profit.by_product" :key="row.id">
                                <td class="px-3.5 py-2.5 font-semibold text-slate-800"><HighlightText :text="row.name" :keyword="profitFilters.search" /></td>
                                <td class="px-3.5 py-2.5 text-right">{{ row.qty_sold }}</td>
                                <td class="px-3.5 py-2.5 text-right">{{ formatMoney(row.revenue) }}</td>
                                <td class="px-3.5 py-2.5 text-right text-slate-500">{{ formatMoney(row.cost) }}</td>
                                <td
                                    class="px-3.5 py-2.5 text-right font-bold"
                                    :class="row.profit >= 0 ? 'text-emerald-600' : 'text-rose-600'"
                                >
                                    {{ formatMoney(row.profit) }}
                                </td>
                            </tr>

                            <tr v-if="!profit.by_product.length">
                                <td colspan="5" class="px-4 py-8 text-center text-slate-400">
                                    Không có dữ liệu phù hợp trong khoảng thời gian và bộ lọc này
                                </td>
                            </tr>
                        </tbody>
                    </table></div>
                </section>

            </template>

            <!-- ================================================= -->
            <!-- TAB: BÁN CHẠY -->
            <!-- ================================================= -->
            <template v-if="activeTab === 'best-sellers'">

                <ListToolbar v-model="bestSellerFilters.search" placeholder="Tìm sản phẩm theo tên hoặc SKU...">
                    <template #filters>
                        <DateRangeFilter v-model:from="filters.from" v-model:to="filters.to" :allow-all="false" align="left" />
                        <ListFilterSelect v-model="bestSellerFilters.category_id" label="Danh mục" :show-placeholder="false" :options="categoryOptions" />
                        <ListFilterSelect v-model="bestSellerFilters.brand_id" label="Thương hiệu" :show-placeholder="false" :options="brandOptions" />
                        <ListFilterSelect v-model="bestSellerOrder" label="Sắp xếp" :show-placeholder="false" :options="bestSellerOrderOptions" />
                        <ListFilterSelect v-model="bestSellerLimit" label="Số dòng" :show-placeholder="false" :options="bestSellerLimitOptions" width="sm:w-[120px]" />
                    </template>
                </ListToolbar>

                <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="overflow-x-auto"><table class="app-data-table w-full min-w-[640px] border-collapse text-left text-xs">
                        <thead>
                            <tr class="select-none border-b border-slate-200 bg-slate-50/80 text-[11px] font-extrabold uppercase tracking-wider text-slate-500">
                                <th class="px-3.5 py-2.5 text-left">#</th>
                                <th class="px-3.5 py-2.5 text-left">Sản phẩm</th>
                                <th class="px-3.5 py-2.5 text-left">SKU</th>
                                <th class="px-3.5 py-2.5 text-right">SL bán</th>
                                <th class="px-3.5 py-2.5 text-right">Doanh thu</th>
                                <th class="px-3.5 py-2.5 text-right">Tồn kho</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs font-medium">
                            <tr class="transition-colors hover:bg-emerald-50/70" v-for="(row, index) in bestSellers.items" :key="row.id">
                                <td class="px-3.5 py-2.5 text-slate-400">{{ index + 1 }}</td>
                                <td class="px-3.5 py-2.5 font-semibold text-slate-800"><HighlightText :text="row.name" :keyword="bestSellerFilters.search" /></td>
                                <td class="px-3.5 py-2.5 text-slate-500"><HighlightText :text="row.sku" :keyword="bestSellerFilters.search" /></td>
                                <td class="px-3.5 py-2.5 text-right font-bold text-blue-600">{{ row.qty_sold }}</td>
                                <td class="px-3.5 py-2.5 text-right">{{ formatMoney(row.revenue) }}</td>
                                <td class="px-3.5 py-2.5 text-right text-slate-500">{{ row.stock }}</td>
                            </tr>

                            <tr v-if="!bestSellers.items.length">
                                <td colspan="6" class="px-4 py-8 text-center text-slate-400">
                                    Không có dữ liệu phù hợp trong khoảng thời gian và bộ lọc này
                                </td>
                            </tr>
                        </tbody>
                    </table></div>
                </section>

            </template>

            <!-- ================================================= -->
            <!-- TAB: HÀNG TỒN -->
            <!-- ================================================= -->
            <template v-if="activeTab === 'inventory'">

                <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="text-xs font-bold uppercase tracking-wide text-slate-500">Tổng sản phẩm</div>
                        <div class="mt-2 text-2xl font-black text-slate-950">
                            {{ inventory.summary?.total_products ?? 0 }}
                        </div>
                    </div>

                    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="text-xs font-bold uppercase tracking-wide text-slate-500">Tổng SL tồn</div>
                        <div class="mt-2 text-2xl font-black text-slate-950">
                            {{ inventory.summary?.total_stock_qty ?? 0 }}
                        </div>
                    </div>

                    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="text-xs font-bold uppercase tracking-wide text-slate-500">Giá trị tồn kho</div>
                        <div class="mt-2 text-2xl font-black text-blue-600">
                            {{ formatMoney(inventory.summary?.total_stock_value) }}
                        </div>
                    </div>

                    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="text-xs font-bold uppercase tracking-wide text-slate-500">Sắp hết hàng</div>
                        <div class="mt-2 text-2xl font-black text-amber-600">
                            {{ inventory.summary?.low_stock_count ?? 0 }}
                        </div>
                    </div>
                </section>

                <ListToolbar v-model="inventoryFilters.search" placeholder="Tìm sản phẩm theo tên, SKU hoặc model...">
                    <template #search-end><BarcodeCameraButton class="absolute right-8 top-1/2 z-10 -translate-y-1/2" @scanned="(code) => inventoryFilters.search = code" /></template>
                    <template #filters>
                        <ListFilterSelect v-model="inventoryFilters.category_id" label="Danh mục" :show-placeholder="false" :options="categoryOptions" />
                        <ListFilterSelect v-model="inventoryFilters.brand_id" label="Thương hiệu" :show-placeholder="false" :options="brandOptions" />
                        <ListFilterSelect v-model="inventoryFilters.stock_status" label="Tình trạng tồn" :show-placeholder="false" :options="stockStatusOptions" />
                    </template>
                </ListToolbar>

                <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="overflow-x-auto"><table class="app-data-table w-full min-w-[640px] border-collapse text-left text-xs">
                        <thead>
                            <tr class="select-none border-b border-slate-200 bg-slate-50/80 text-[11px] font-extrabold uppercase tracking-wider text-slate-500">
                                <th class="px-3.5 py-2.5 text-left">Sản phẩm</th>
                                <th class="px-3.5 py-2.5 text-left">Danh mục</th>
                                <th class="px-3.5 py-2.5 text-right">Tồn kho</th>
                                <th class="px-3.5 py-2.5 text-right">Ngưỡng cảnh báo</th>
                                <th class="px-3.5 py-2.5 text-right">Giá vốn</th>
                                <th class="px-3.5 py-2.5 text-right">Giá trị tồn</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs font-medium">
                            <tr
                                v-for="row in inventory.products?.data ?? []"
                                :key="row.id"
                                :class="row.stock <= row.alert_stock ? 'bg-amber-50/60' : ''"
                            >
                                <td class="px-3.5 py-2.5 font-semibold text-slate-800">{{ row.name }}</td>
                                <td class="px-3.5 py-2.5 text-slate-500">{{ row.category?.name ?? '—' }}</td>
                                <td
                                    class="px-3.5 py-2.5 text-right font-bold"
                                    :class="row.stock <= row.alert_stock ? 'text-amber-600' : 'text-slate-800'"
                                >
                                    {{ row.stock }}
                                </td>
                                <td class="px-3.5 py-2.5 text-right text-slate-500">{{ row.alert_stock }}</td>
                                <td class="px-3.5 py-2.5 text-right">{{ formatMoney(row.cost_price) }}</td>
                                <td class="px-3.5 py-2.5 text-right font-bold text-blue-600">
                                    {{ formatMoney(row.stock_value) }}
                                </td>
                            </tr>

                            <tr v-if="!inventory.products?.data?.length">
                                <td colspan="6" class="px-4 py-8 text-center text-slate-400">
                                    Không tìm thấy sản phẩm nào
                                </td>
                            </tr>
                        </tbody>
                    </table></div>

                    <div
                        v-if="inventory.products?.last_page > 1"
                        class="flex items-center justify-between border-t border-[#c5d4e3] bg-[#dce7f2] px-4 py-3 text-sm"
                    >
                        <div class="text-[#40566d]">
                            Trang {{ inventory.products.current_page }} / {{ inventory.products.last_page }}
                        </div>

                        <div class="flex gap-2">
                            <button
                                type="button"
                                class="min-w-9 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-600 transition hover:border-emerald-300 hover:bg-emerald-50 disabled:cursor-not-allowed disabled:opacity-40"
                                :disabled="inventory.products.current_page <= 1"
                                @click="loadInventory(inventory.products.current_page - 1)"
                            >
                                Trước
                            </button>
                            <button
                                type="button"
                                class="min-w-9 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-600 transition hover:border-emerald-300 hover:bg-emerald-50 disabled:cursor-not-allowed disabled:opacity-40"
                                :disabled="inventory.products.current_page >= inventory.products.last_page"
                                @click="loadInventory(inventory.products.current_page + 1)"
                            >
                                Sau
                            </button>
                        </div>
                    </div>
                </section>

            </template>

            <!-- ================================================= -->
            <!-- TAB: CÔNG NỢ -->
            <!-- ================================================= -->
            <template v-if="activeTab === 'debts'">

                <section class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="text-xs font-bold uppercase tracking-wide text-slate-500">
                            Tổng công nợ {{ debtFilters.type === 'customer' ? 'khách hàng' : 'nhà cung cấp' }}
                        </div>
                        <div class="mt-2 text-2xl font-black text-rose-600">
                            {{ formatMoney(debts.summary?.total_debt) }}
                        </div>
                    </div>

                    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="text-xs font-bold uppercase tracking-wide text-slate-500">Số lượng đang nợ</div>
                        <div class="mt-2 text-2xl font-black text-slate-950">
                            {{ debts.summary?.count ?? 0 }}
                        </div>
                    </div>
                </section>

                <ListToolbar v-model="debtFilters.search" placeholder="Tìm theo tên, mã hoặc điện thoại...">
                    <template #filters><ListFilterSelect v-model="debtFilters.level" label="Mức nợ" :show-placeholder="false" :options="debtLevelOptions" /><div class="flex h-[42px] overflow-hidden rounded-xl border border-slate-200">
                        <button
                            type="button"
                            class="px-4 py-2 text-xs font-bold"
                            :class="debtFilters.type === 'customer' ? 'bg-blue-600 text-white' : 'bg-white text-slate-600'"
                            @click="debtFilters.type = 'customer'"
                        >
                            Khách hàng
                        </button>
                        <button
                            type="button"
                            class="px-4 py-2 text-xs font-bold"
                            :class="debtFilters.type === 'supplier' ? 'bg-blue-600 text-white' : 'bg-white text-slate-600'"
                            @click="debtFilters.type = 'supplier'"
                        >
                            Nhà cung cấp
                        </button>
                    </div></template>
                </ListToolbar>

                <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="overflow-x-auto"><table class="app-data-table w-full min-w-[640px] border-collapse text-left text-xs">
                        <thead>
                            <tr class="select-none border-b border-slate-200 bg-slate-50/80 text-[11px] font-extrabold uppercase tracking-wider text-slate-500">
                                <th class="px-3.5 py-2.5 text-left">Mã</th>
                                <th class="px-3.5 py-2.5 text-left">
                                    {{ debtFilters.type === 'customer' ? 'Khách hàng' : 'Nhà cung cấp' }}
                                </th>
                                <th class="px-3.5 py-2.5 text-left">Điện thoại</th>
                                <th class="px-3.5 py-2.5 text-right">Số nợ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs font-medium">
                            <tr class="transition-colors hover:bg-emerald-50/70" v-for="row in debts.items?.data ?? []" :key="row.id">
                                <td class="px-3.5 py-2.5 text-slate-500"><HighlightText :text="row.code" :keyword="debtFilters.search || ''" /></td>
                                <td class="px-3.5 py-2.5 font-semibold text-slate-800">
                                    <HighlightText :text="row.full_name ?? row.name" :keyword="debtFilters.search || ''" />
                                </td>
                                <td class="px-3.5 py-2.5 text-slate-500"><HighlightText v-if="row.phone" :text="row.phone" :keyword="debtFilters.search || ''" /><template v-else>—</template></td>
                                <td class="px-3.5 py-2.5 text-right font-bold text-rose-600">
                                    {{ formatMoney(row.debt_balance) }}
                                </td>
                            </tr>

                            <tr v-if="!debts.items?.data?.length">
                                <td colspan="4" class="px-4 py-8 text-center text-slate-400">
                                    Không có công nợ nào
                                </td>
                            </tr>
                        </tbody>
                    </table></div>

                    <div
                        v-if="debts.items?.last_page > 1"
                        class="flex items-center justify-between border-t border-[#c5d4e3] bg-[#dce7f2] px-4 py-3 text-sm"
                    >
                        <div class="text-[#40566d]">
                            Trang {{ debts.items.current_page }} / {{ debts.items.last_page }}
                        </div>

                        <div class="flex gap-2">
                            <button
                                type="button"
                                class="min-w-9 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-600 transition hover:border-emerald-300 hover:bg-emerald-50 disabled:cursor-not-allowed disabled:opacity-40"
                                :disabled="debts.items.current_page <= 1"
                                @click="loadDebts(debts.items.current_page - 1)"
                            >
                                Trước
                            </button>
                            <button
                                type="button"
                                class="min-w-9 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-600 transition hover:border-emerald-300 hover:bg-emerald-50 disabled:cursor-not-allowed disabled:opacity-40"
                                :disabled="debts.items.current_page >= debts.items.last_page"
                                @click="loadDebts(debts.items.current_page + 1)"
                            >
                                Sau
                            </button>
                        </div>
                    </div>
                </section>

            </template>

        </div>

    </AuthenticatedLayout>
</template>
