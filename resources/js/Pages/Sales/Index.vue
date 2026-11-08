<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import {
    Search,
    Printer,
    FileText,
    DollarSign,
    CreditCard,
    AlertTriangle,
    CheckCircle2,
    XCircle,
    Undo2,
    ArrowUpDown,
    ArrowUp,
    ArrowDown,
    Pencil,
    LoaderCircle,
    Clock,
    Phone,
    ChevronRight,
    X,
    CalendarDays,
    ChevronLeft,
} from 'lucide-vue-next'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import DataPanel from '@/Components/UI/DataPanel.vue'
import BaseModal from '@/Components/UI/BaseModal.vue'
import InvoiceDetailModal from '@/Components/InvoiceDetailModal.vue'
import SaleEditModal from '@/Components/SaleEditModal.vue'
import PaginationBar from '@/Components/UI/PaginationBar.vue'
import PageHeader from '@/Components/UI/PageHeader.vue'
import FloatingSelect from '@/Components/UI/FloatingSelect.vue'
import { formatDateTime, formatMoney } from '@/utils/format'
import api from '@/Services/api'

defineOptions({
    layout: AdminLayout
})

const props = defineProps({
    sales: Object,
    filters: Object,
    selectedSale: { type: Object, default: null },
    stats: {
        type: Object,
        default: () => ({
            totalRevenue: 0,
            totalOrders: 0,
            totalDebt: 0
        })
    }
})


// Kiểm tra quyền theo hành động thay vì suy ra từ tên vai trò.
const page = usePage()
const canCancelSales = computed(() => page.props.auth?.permissions?.includes('sales.cancel') ?? false)
const canEditSales = computed(() => page.props.auth?.permissions?.includes('sales.edit') ?? false)
const salesData = ref(props.sales)
const statsData = ref(props.stats)

watch(() => props.sales, value => { salesData.value = value })
watch(() => props.stats, value => { statsData.value = value })

// Cập nhật lại các ref khi props.filters thay đổi từ Server gửi về
watch(
    () => props.filters,
    (newFilters) => {
        if (newFilters) {
            search.value = newFilters.search ?? ''
            status.value = newFilters.status ?? ''
            paymentStatus.value = newFilters.payment_status ?? ''
            dateFrom.value = newFilters.date_from ?? newFilters.start_date ?? ''
            dateTo.value = newFilters.date_to ?? newFilters.end_date ?? ''
            sortKey.value = newFilters.sort_by ?? 'created_at'
            sortOrder.value = newFilters.sort_order ?? 'desc'
            perPage.value = Number(newFilters.per_page ?? 10)
        }
    },
    { deep: true }
)

// Lấy giá trị khởi tạo từ URL / Props
const search = ref(props.filters?.search ?? '')
const status = ref(props.filters?.status ?? '')
const paymentStatus = ref(props.filters?.payment_status ?? '')
const dateFrom = ref(props.filters?.date_from ?? '')
const dateTo = ref(props.filters?.date_to ?? '')
const datePreset = ref(dateFrom.value || dateTo.value ? 'custom' : 'all')
const calendarOpen = ref(false)
const calendarRef = ref(null)
const pickerStart = ref('')
const pickerEnd = ref('')
const calendarMonth = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1))
const perPage = ref(Number(props.filters?.per_page ?? 10))

const detailSale = ref(null)
const editSale = ref(null)
const cancelReason = ref('')
const showCancelModal = ref(false)
const selectedSaleForCancel = ref(null)
const printFrameUrl = ref('')
const printFrameKey = ref(0)

watch(() => props.selectedSale, (sale) => {
    const saleIdInUrl = new URL(window.location.href).searchParams.get('open_sale')
    detailSale.value = sale && String(sale.id) === saleIdInUrl ? sale : null
}, { immediate: true })

const syncSaleDetailFromUrl = () => {
    const saleId = new URL(window.location.href).searchParams.get('open_sale')
    if (!saleId) detailSale.value = null
    else if (String(detailSale.value?.id) !== saleId) {
        detailSale.value = salesData.value?.data?.find(sale => String(sale.id) === saleId) || props.selectedSale || null
    }
}
window.addEventListener('popstate', syncSaleDetailFromUrl)
onBeforeUnmount(() => window.removeEventListener('popstate', syncSaleDetailFromUrl))

const closeSaleDetail = () => {
    detailSale.value = null
    const url = new URL(window.location.href)
    if (!url.searchParams.has('open_sale')) return
    url.searchParams.delete('open_sale')
    window.history.replaceState(window.history.state, '', `${url.pathname}${url.search}${url.hash}`)
}

const openSaleDetail = (sale) => {
    if (!sale?.id) return
    const url = new URL(window.location.href)
    if (url.searchParams.get('open_sale') !== String(sale.id)) {
        url.searchParams.set('open_sale', String(sale.id))
        window.history.pushState(window.history.state, '', `${url.pathname}${url.search}${url.hash}`)
    }
    detailSale.value = sale
}

// --- QUẢN LÝ SORTING (SẮP XẾP BẢNG) ---
const sortKey = ref(props.filters?.sort_by ?? 'created_at')
const sortOrder = ref(props.filters?.sort_order ?? 'desc')
let searchTimeout = null
let salesRequestController = null
let salesRequestId = 0
const loadingSales = ref(false)
onBeforeUnmount(() => {
    clearTimeout(searchTimeout)
    salesRequestController?.abort()
    window.removeEventListener('pointerdown', closeCalendarOnOutside)
})

const syncFilterUrl = (filters) => {
    const url = new URL(window.location.href)
    const values = {
        search: filters.search,
        status: filters.status,
        payment_status: filters.payment_status,
        date_from: filters.date_from,
        date_to: filters.date_to,
        sort_by: filters.sort_by,
        sort_order: filters.sort_order,
        per_page: filters.per_page,
    }
    Object.entries(values).forEach(([key, value]) => {
        if (value === '' || value === null || value === undefined) url.searchParams.delete(key)
        else url.searchParams.set(key, String(value))
    })
    url.searchParams.delete('page')
    window.history.replaceState(window.history.state, '', `${url.pathname}${url.search}${url.hash}`)
}

const requestSales = async () => {
    salesRequestController?.abort()
    const requestId = ++salesRequestId
    salesRequestController = new AbortController()
    loadingSales.value = true
    const params = {
        search: search.value || undefined,
        status: status.value || undefined,
        payment_status: paymentStatus.value || undefined,
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
        sort_by: sortKey.value,
        sort_order: sortOrder.value,
        per_page: perPage.value,
        page: 1,
    }

    try {
        const { data } = await api.get(route('sales.index'), {
            params,
            signal: salesRequestController.signal,
        })
        if (requestId !== salesRequestId) return
        salesData.value = data.sales
        statsData.value = data.stats
        syncFilterUrl(data.filters || params)
    } catch (error) {
        if (error.name !== 'CanceledError' && requestId === salesRequestId) console.error(error)
    } finally {
        if (requestId === salesRequestId) loadingSales.value = false
    }
}

const handleSort = (key) => {
    if (sortKey.value === key) {
        sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc'
    } else {
        sortKey.value = key
        sortOrder.value = 'asc'
    }
    clearTimeout(searchTimeout)
    requestSales()
}

// --- QUẢN LÝ TÍNH NĂNG CLICK LỌC NHANH ---
const filterByCustomer = (customerName) => {
    if (!customerName || customerName === 'Khách lẻ') return
    search.value = customerName
    handleFilterChange()
}

const filterByCashier = (cashierName) => {
    if (!cashierName) return
    search.value = cashierName
    handleFilterChange()
}

const filterByStatus = (statusValue) => {
    status.value = statusValue
    paymentStatus.value = ''
    handleFilterChange()
}

const applyPaymentStatus = (value) => {
    paymentStatus.value = value
    status.value = ''
    clearTimeout(searchTimeout)
    requestSales()
}

const paymentStatusOptions = [
    { label: 'Tất cả trạng thái', value: '' },
    { label: 'Đã thanh toán', value: 'paid' },
    { label: 'Còn nợ', value: 'debt' },
]

const clearSearch = () => {
    search.value = ''
    clearTimeout(searchTimeout)
    requestSales()
}

const datePresetOptions = [
    { label: 'Toàn thời gian', value: 'all' },
    { label: 'Hôm nay', value: 'today' },
    { label: 'Hôm qua', value: 'yesterday' },
    { label: '7 ngày qua', value: 'last_7_days' },
    { label: '30 ngày qua', value: 'last_30_days' },
    { label: 'Tháng này', value: 'this_month' },
    { label: 'Tháng trước', value: 'last_month' },
]

const dateInputValue = (date) => {
    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')
    return `${year}-${month}-${day}`
}

const dateFromInput = value => value ? new Date(`${value}T12:00:00`) : null
const dateRangeFor = (preset) => {
    const today = new Date()
    if (preset === 'all') return { from: '', to: '' }
    if (preset === 'today') {
        const value = dateInputValue(today)
        return { from: value, to: value }
    }
    if (preset === 'yesterday') {
        const yesterday = new Date(today)
        yesterday.setDate(today.getDate() - 1)
        const value = dateInputValue(yesterday)
        return { from: value, to: value }
    }
    if (preset === 'last_7_days') {
        const from = new Date(today)
        from.setDate(today.getDate() - 6)
        return { from: dateInputValue(from), to: dateInputValue(today) }
    }
    if (preset === 'last_30_days') {
        const from = new Date(today)
        from.setDate(today.getDate() - 29)
        return { from: dateInputValue(from), to: dateInputValue(today) }
    }
    if (preset === 'this_month') return { from: dateInputValue(new Date(today.getFullYear(), today.getMonth(), 1)), to: dateInputValue(today) }
    if (preset === 'last_month') return { from: dateInputValue(new Date(today.getFullYear(), today.getMonth() - 1, 1)), to: dateInputValue(new Date(today.getFullYear(), today.getMonth(), 0)) }
    return null
}
const applyDatePreset = (preset) => {
    datePreset.value = preset
    const range = dateRangeFor(preset)
    if (!range) return
    dateFrom.value = range.from
    dateTo.value = range.to
    calendarOpen.value = false
    clearTimeout(searchTimeout)
    requestSales()
}
const stageDatePreset = (preset) => {
    datePreset.value = preset
    const range = dateRangeFor(preset)
    if (!range) return
    pickerStart.value = range.from
    pickerEnd.value = range.to
    if (range.from) {
        const anchor = dateFromInput(range.from)
        calendarMonth.value = new Date(anchor.getFullYear(), anchor.getMonth(), 1)
    }
}
const monthLabel = month => new Intl.DateTimeFormat('vi-VN', { month: 'long', year: 'numeric' }).format(month)
const calendarCellsFor = (month) => {
    const first = new Date(month.getFullYear(), month.getMonth(), 1)
    const start = new Date(first)
    start.setDate(1 - ((first.getDay() + 6) % 7))
    return Array.from({ length: 42 }, (_, index) => {
        const date = new Date(start)
        date.setDate(start.getDate() + index)
        return { key: dateInputValue(date), day: date.getDate(), currentMonth: date.getMonth() === month.getMonth() }
    })
}
const calendarCells = computed(() => calendarCellsFor(calendarMonth.value))
const isPickerSelected = key => key === pickerStart.value || key === pickerEnd.value
const isPickerInRange = key => Boolean(pickerStart.value && pickerEnd.value && key > pickerStart.value && key < pickerEnd.value)
const pickerRangeLabel = computed(() => {
    const format = value => new Intl.DateTimeFormat('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(dateFromInput(value))
    if (!pickerStart.value) return 'Chọn ngày bắt đầu, rồi chọn ngày kết thúc'
    return pickerEnd.value ? `${format(pickerStart.value)} – ${format(pickerEnd.value)}` : `Từ ${format(pickerStart.value)} · chọn ngày kết thúc`
})
const selectedDateRangeLabel = computed(() => {
    const format = value => new Intl.DateTimeFormat('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(dateFromInput(value))
    if (!dateFrom.value && !dateTo.value) return 'Toàn thời gian'
    if (!dateTo.value || dateTo.value === dateFrom.value) return format(dateFrom.value || dateTo.value)
    return `${format(dateFrom.value)} – ${format(dateTo.value)}`
})
const openDatePicker = () => {
    pickerStart.value = dateFrom.value
    pickerEnd.value = dateTo.value
    const anchor = dateFromInput(pickerStart.value) || new Date()
    calendarMonth.value = new Date(anchor.getFullYear(), anchor.getMonth(), 1)
    calendarOpen.value = true
}
const moveCalendarMonth = amount => { calendarMonth.value = new Date(calendarMonth.value.getFullYear(), calendarMonth.value.getMonth() + amount, 1) }
const selectPickerDate = (key) => {
    if (!pickerStart.value || pickerEnd.value) { pickerStart.value = key; pickerEnd.value = ''; return }
    if (key < pickerStart.value) { pickerEnd.value = pickerStart.value; pickerStart.value = key } else pickerEnd.value = key
}
const applyCustomDateRange = () => {
    if (datePreset.value === 'all') {
        applyDatePreset('all')
        return
    }
    if (!pickerStart.value) return
    datePreset.value = 'custom'
    dateFrom.value = pickerStart.value
    dateTo.value = pickerEnd.value || pickerStart.value
    calendarOpen.value = false
    clearTimeout(searchTimeout)
    requestSales()
}
const clearDateRange = () => applyDatePreset('all')
const closeCalendarOnOutside = event => {
    if (calendarOpen.value && calendarRef.value && !calendarRef.value.contains(event.target)) calendarOpen.value = false
}
onMounted(() => window.addEventListener('pointerdown', closeCalendarOnOutside))

// Hàm kích hoạt lọc (chỉ gửi request khi có thay đổi)
const handleFilterChange = () => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(requestSales, 180)
}

// Tính tổng tiền hóa đơn
const saleTotal = (sale) => sale.grand_total ?? sale.total_amount ?? sale.subtotal ?? 0
const salePaidAmount = (sale) => Math.max(0, Number(sale?.paid_amount || 0) - Number(sale?.old_debt_paid || 0))
const saleDebtAmount = (sale) => sale?.status === 'cancelled'
    ? 0
    : Math.max(0, Number(saleTotal(sale) || 0) - salePaidAmount(sale))
const saleHasDebt = (sale) => sale?.status !== 'cancelled'
    && saleDebtAmount(sale) > 0.009

// Lấy tên khách hàng
const getCustomerName = (sale) => {
    if (!sale?.customer) return 'Khách lẻ'
    return sale.customer.full_name || sale.customer.name || 'Khách lẻ'
}
const getCustomerPhone = (sale) => sale?.customer?.phone || 'Không có SĐT'

// In hóa đơn
const openPrintWindow = (saleId) => {
    printFrameUrl.value = route('sales.receipt', saleId)
    printFrameKey.value++
}

const printReceiptFrame = (event) => {
    const frame = event.target
    const frameWindow = frame?.contentWindow
    const frameDocument = frame?.contentDocument
    if (!frameWindow || !frameDocument) return

    const settings = page.props.settings || {}
    const paper = settings.print_paper_size || 'a4'
    const landscape = settings.print_orientation === 'landscape'
    const sizes = { a4: 'A4', a5: 'A5', '80mm': '80mm 200mm', '58mm': '58mm 150mm' }
    const thermal = paper === '80mm' || paper === '58mm'
    const width = paper === '58mm' ? '54mm'
        : paper === '80mm' ? '76mm'
            : paper === 'a5' ? (landscape ? '190mm' : '128mm')
                : (landscape ? '277mm' : '190mm')

    let style = frameDocument.getElementById('direct-print-settings')
    if (!style) {
        style = frameDocument.createElement('style')
        style.id = 'direct-print-settings'
        frameDocument.head.appendChild(style)
    }
    style.textContent = `.no-print,.print-bar,.btn-print{display:none!important} :root{--configured-receipt-width:${width}} @page{size:${sizes[paper] || 'A4'}${thermal ? '' : ` ${landscape ? 'landscape' : 'portrait'}`};margin:${thermal ? '0' : '10mm'}} .receipt-container{width:var(--configured-receipt-width)!important;margin-left:auto!important;margin-right:auto!important}`

    frameWindow.addEventListener('afterprint', () => {
        printFrameUrl.value = ''
    }, { once: true })
    window.setTimeout(() => frameWindow.print(), 120)
}

// Xác nhận Hủy
const confirmCancel = (sale) => {
    selectedSaleForCancel.value = sale
    cancelReason.value = ''
    showCancelModal.value = true
}

// Xử lý Hủy hóa đơn
const handleCancelSale = () => {
    if (!selectedSaleForCancel.value) return
    const reasonText = cancelReason.value.trim()

    router.post(
        route('sales.cancel', selectedSaleForCancel.value.id),
        { reason: reasonText, cancel_reason: reasonText },
        {
            onSuccess: () => {
                showCancelModal.value = false
                selectedSaleForCancel.value = null
                cancelReason.value = ''
                router.reload({ only: ['sales'] })
            }
        }
    )
}

const onSaleEdited = () => {
    editSale.value = null
    router.reload({ only: ['sales'], preserveScroll: true })
}

// Lý do hủy mẫu
const defaultReasons = [
    'Khách đổi ý không mua nữa',
    'Đổi sang sản phẩm khác',
    'Nhầm sản phẩm / số lượng',
    'Nhập sai giá / giảm giá',
    'Chưa nhận được tiền',
    'Trùng hóa đơn'
]

const predefinedCancelReasons = computed(() => {
    const existingReasons = (salesData.value?.data || [])
        .map(s => s.cancel_reason || s.reason)
        .filter(Boolean)

    return Array.from(new Set([...defaultReasons, ...existingReasons]))
})

const sortedSalesData = computed(() => salesData.value?.data || [])
const hasActiveSearch = computed(() => search.value.trim().length > 0)
const activeFilters = computed(() => ({
    search: search.value,
    status: status.value,
    payment_status: paymentStatus.value,
    date_from: dateFrom.value,
    date_to: dateTo.value,
    sort_by: sortKey.value,
    sort_order: sortOrder.value,
    per_page: perPage.value,
}))
const searchableText = (value) => {
    const text = String(value || '')
    let normalized = ''
    const sourceIndexes = []

    Array.from(text).forEach((character, sourceIndex) => {
        const folded = character
            .normalize('NFD')
            .replace(/\p{Diacritic}/gu, '')
            .replace(/đ/g, 'd')
            .replace(/Đ/g, 'D')
            .toLocaleLowerCase('vi-VN')

        Array.from(folded).forEach((foldedCharacter) => {
            normalized += foldedCharacter
            sourceIndexes.push(sourceIndex)
        })
    })

    return { text, normalized, sourceIndexes }
}
const normalizeSearchText = (value) => searchableText(value).normalized
const saleSearchMatches = (sale) => {
    const keyword = normalizeSearchText(search.value.trim())
    if (!keyword) return []

    const candidates = [
        { label: 'Số điện thoại', text: sale.customer?.phone || '' },
        ...(sale.items || []).flatMap(item => [
            { label: 'Sản phẩm', text: item.product?.name || '' },
            { label: 'IMEI', text: item.product_imei?.imei || '' },
            { label: 'Serial', text: item.product_imei?.serial || '' },
        ]),
    ]
    const seen = new Set()

    return candidates.filter(candidate => {
        const key = `${candidate.label}:${candidate.text}`
        if (!candidate.text || seen.has(key) || !normalizeSearchText(candidate.text).includes(keyword)) return false
        seen.add(key)
        return true
    }).slice(0, 3)
}
const highlightedSearchSegments = (value) => {
    const searchable = searchableText(value)
    const keyword = normalizeSearchText(search.value.trim())
    if (!keyword) return [{ text: searchable.text, match: false }]

    const normalizedStart = searchable.normalized.indexOf(keyword)
    if (normalizedStart < 0) return [{ text: searchable.text, match: false }]

    const start = searchable.sourceIndexes[normalizedStart]
    const endIndex = normalizedStart + keyword.length - 1
    const end = (searchable.sourceIndexes[endIndex] ?? start) + 1

    return [
        { text: searchable.text.slice(0, start), match: false },
        { text: searchable.text.slice(start, end), match: true },
        { text: searchable.text.slice(end), match: false },
    ].filter(segment => segment.text)
}
</script>

<template>
    <!-- THU NHỎ MARGIN / PADDING NGOÀI (p-2.5 sm:p-3 space-y-3) -->
    <div class="space-y-3 font-sans text-slate-800">
        <PageHeader title="Hóa đơn bán hàng" />
        <DataPanel detached>
            <template #header>
                <div class="flex w-full flex-col items-stretch gap-2.5 rounded-2xl border border-slate-200/80 bg-white p-3.5 shadow-sm sm:flex-row sm:flex-wrap sm:items-center sm:p-4">
                    <div class="relative min-w-0 w-full sm:min-w-[220px] sm:flex-1">
                            <Search class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input id="sales-search" v-model="search" type="text" autocomplete="off" placeholder="Tìm Mã HĐ, Tên KH, SĐT, sản phẩm, Serial/IMEI..." class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-10 text-sm font-normal text-slate-800 outline-none transition focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-100" @input="handleFilterChange" />
                            <LoaderCircle v-if="loadingSales" class="pointer-events-none absolute right-3.5 top-1/2 h-4 w-4 -translate-y-1/2 animate-spin text-emerald-600" />
                            <button v-else-if="search" type="button" class="absolute right-3 top-1/2 flex h-5 w-5 -translate-y-1/2 items-center justify-center rounded-full bg-slate-200 text-slate-500 transition hover:bg-slate-300" title="Xóa từ khóa" @click="clearSearch"><X class="h-3 w-3" /></button>
                    </div>

                    <FloatingSelect
                        :model-value="paymentStatus"
                        label="Trạng thái thanh toán"
                        :options="paymentStatusOptions"
                        input-class="!h-[42px] !rounded-xl !border-slate-200 !px-3 !pb-1 !pt-3 !text-xs !font-semibold focus:!border-blue-400"
                        wrapper-class="w-full sm:w-[180px]"
                        @update:model-value="applyPaymentStatus"
                    />
                    <div ref="calendarRef" class="relative w-full min-w-0 shrink-0 sm:w-auto">
                            <button type="button" class="inline-flex h-[42px] w-full min-w-0 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 text-left text-xs font-semibold text-slate-700 shadow-sm transition hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700 sm:min-w-[220px] sm:w-auto" title="Lọc theo khoảng ngày" @click="calendarOpen ? calendarOpen = false : openDatePicker()"><CalendarDays class="h-4 w-4 shrink-0 text-blue-600" /><span class="min-w-0 flex-1 truncate">{{ selectedDateRangeLabel }}</span><ChevronRight class="h-4 w-4 shrink-0 rotate-90 text-slate-400" /></button>
                            <div v-if="calendarOpen" class="absolute right-0 top-full z-50 mt-2 w-full overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl sm:w-[470px]">
                                <div class="flex flex-col sm:flex-row">
                                    <aside class="border-b border-slate-200 bg-slate-50/70 p-2 sm:w-40 sm:shrink-0 sm:border-b-0 sm:border-r">
                                        <p class="mb-1 px-2 pt-1 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Khoảng có sẵn</p>
                                        <div class="flex flex-wrap gap-1 sm:block sm:space-y-0.5">
                                            <button v-for="preset in datePresetOptions" :key="preset.value" type="button" class="w-auto rounded-lg px-2.5 py-1.5 text-left text-[11px] font-semibold transition sm:w-full sm:px-2.5 sm:py-2" :class="datePreset === preset.value ? 'bg-blue-100 text-blue-700' : 'text-slate-600 hover:bg-white hover:text-slate-900'" @click="stageDatePreset(preset.value)">{{ preset.label }}</button>
                                        </div>
                                    </aside>
                                    <section class="min-w-0 flex-1 p-2.5 sm:p-3">
                                        <header class="mb-2 flex items-center justify-between gap-2"><button type="button" class="grid h-8 w-8 place-items-center rounded-lg text-slate-600 transition hover:bg-slate-100" aria-label="Tháng trước" @click="moveCalendarMonth(-1)"><ChevronLeft class="h-4 w-4" /></button><div class="min-w-0 flex-1 text-center text-xs font-bold capitalize text-slate-800">{{ monthLabel(calendarMonth) }}</div><button type="button" class="grid h-8 w-8 place-items-center rounded-lg text-slate-600 transition hover:bg-slate-100" aria-label="Tháng sau" @click="moveCalendarMonth(1)"><ChevronRight class="h-4 w-4" /></button></header>
                                        <div class="grid grid-cols-7 text-center text-[10px] font-bold text-slate-400"><span v-for="day in ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN']" :key="day" class="py-1">{{ day }}</span></div><div class="grid grid-cols-7"><button v-for="cell in calendarCells" :key="cell.key" type="button" class="relative mx-auto my-px grid h-8 w-8 place-items-center rounded-md text-[11px] transition" :class="isPickerSelected(cell.key) ? 'bg-blue-600 font-bold text-white shadow-sm hover:bg-blue-700' : isPickerInRange(cell.key) ? 'rounded-none bg-blue-100 text-blue-800 hover:bg-blue-200' : cell.currentMonth ? 'text-slate-700 hover:bg-slate-100' : 'text-slate-400 hover:bg-slate-100'" @click="selectPickerDate(cell.key)">{{ cell.day }}</button></div>
                                    </section>
                                </div>
                                <footer class="flex flex-col gap-2 border-t border-slate-200 bg-slate-50/50 px-3 py-2.5 sm:flex-row sm:items-center sm:justify-between"><p class="truncate text-xs font-medium text-slate-600">Đã chọn: <span class="font-bold text-slate-800">{{ pickerRangeLabel }}</span></p><div class="flex items-center justify-end gap-2"><button type="button" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-white hover:text-rose-600" @click="clearDateRange">Xóa</button><button type="button" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-white" @click="calendarOpen = false">Hủy</button><button type="button" :disabled="datePreset !== 'all' && !pickerStart" class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-bold text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50" @click="applyCustomDateRange">Áp dụng</button></div></footer>
                            </div>
                    </div>
                </div>
            </template>

            <!-- DANH SÁCH BẢNG DỮ LIỆU CÓ SORT & CLICK LỌC -->
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="hidden overflow-x-auto md:block">
                <table class="app-data-table w-full min-w-[850px] border-collapse text-left text-xs">
                    <thead>
                        <tr class="select-none border-b border-slate-200 bg-slate-50/80 text-[11px] font-extrabold uppercase tracking-wider text-slate-500">
                            
                            <!-- Mã HĐ Sort -->
                            <th class="px-3.5 py-2.5 cursor-pointer hover:bg-slate-100 transition" @click="handleSort('code')">
                                <div class="flex items-center gap-1">
                                    <span>Mã HD</span>
                                    <ArrowUp v-if="sortKey === 'code' && sortOrder === 'asc'" :size="13" class="text-emerald-700" />
                                    <ArrowDown v-else-if="sortKey === 'code' && sortOrder === 'desc'" :size="13" class="text-emerald-700" />
                                    <ArrowUpDown v-else :size="13" class="text-slate-300" />
                                </div>
                            </th>

                            <!-- Khách Hàng Sort -->
                            <th class="px-3.5 py-2.5 cursor-pointer hover:bg-slate-100 transition" @click="handleSort('customer')">
                                <div class="flex items-center gap-1">
                                    <span>Khách Hàng</span>
                                    <ArrowUp v-if="sortKey === 'customer' && sortOrder === 'asc'" :size="13" class="text-emerald-700" />
                                    <ArrowDown v-else-if="sortKey === 'customer' && sortOrder === 'desc'" :size="13" class="text-emerald-700" />
                                    <ArrowUpDown v-else :size="13" class="text-slate-300" />
                                </div>
                            </th>

                            <!-- Thu Ngân Sort -->
                            <th class="px-3.5 py-2.5 cursor-pointer hover:bg-slate-100 transition" @click="handleSort('user')">
                                <div class="flex items-center gap-1">
                                    <span>Thu Ngân</span>
                                    <ArrowUp v-if="sortKey === 'user' && sortOrder === 'asc'" :size="13" class="text-emerald-700" />
                                    <ArrowDown v-else-if="sortKey === 'user' && sortOrder === 'desc'" :size="13" class="text-emerald-700" />
                                    <ArrowUpDown v-else :size="13" class="text-slate-300" />
                                </div>
                            </th>

                            <!-- Tổng Tiền Sort -->
                            <th class="px-3.5 py-2.5 text-right cursor-pointer hover:bg-slate-100 transition" @click="handleSort('total_amount')">
                                <div class="flex items-center justify-end gap-1">
                                    <span>Tổng Tiền</span>
                                    <ArrowUp v-if="sortKey === 'total_amount' && sortOrder === 'asc'" :size="13" class="text-emerald-700" />
                                    <ArrowDown v-else-if="sortKey === 'total_amount' && sortOrder === 'desc'" :size="13" class="text-emerald-700" />
                                    <ArrowUpDown v-else :size="13" class="text-slate-300" />
                                </div>
                            </th>

                            <th class="px-3.5 py-2.5 text-center">Trạng Thái</th>

                            <!-- Ngày Tạo Sort -->
                            <th class="px-3.5 py-2.5 cursor-pointer hover:bg-slate-100 transition" @click="handleSort('created_at')">
                                <div class="flex items-center gap-1">
                                    <span>Ngày Tạo</span>
                                    <ArrowUp v-if="sortKey === 'created_at' && sortOrder === 'asc'" :size="13" class="text-emerald-700" />
                                    <ArrowDown v-else-if="sortKey === 'created_at' && sortOrder === 'desc'" :size="13" class="text-emerald-700" />
                                    <ArrowUpDown v-else :size="13" class="text-slate-300" />
                                </div>
                            </th>

                            <th class="w-24 px-2 py-2.5 text-center">Thao Tác</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100 text-xs font-medium">
                        <tr
                            v-for="sale in sortedSalesData"
                            :key="sale.id"
                            class="cursor-pointer transition-colors hover:bg-emerald-50/70"
                            title="Bấm vào dòng để xem thông tin hóa đơn"
                            @click="openSaleDetail(sale)"
                        >
                            <!-- 1. Mã HD (CLICK XEM CHI TIẾT) -->
                            <td class="px-3.5 py-2.5">
                                <button
                                    type="button"
                                    class="cursor-pointer text-xs font-bold text-emerald-700 hover:text-emerald-900 hover:underline"
                                    title="Bấm vào dòng để xem hóa đơn"
                                >
                                    <template
                                        v-for="(segment, segmentIndex) in highlightedSearchSegments(`#${sale.code || ''}`)"
                                        :key="segmentIndex"
                                    >
                                        <mark
                                            v-if="segment.match"
                                            class="rounded bg-amber-200 px-0.5 font-bold text-amber-950"
                                        >{{ segment.text }}</mark>
                                        <span v-else>{{ segment.text }}</span>
                                    </template>
                                </button>
                                <div
                                    v-if="hasActiveSearch && saleSearchMatches(sale).length"
                                    class="mt-1 max-w-[260px] space-y-0.5 text-[10px] font-medium text-slate-500"
                                >
                                    <div
                                        v-for="match in saleSearchMatches(sale)"
                                        :key="`${match.label}-${match.text}`"
                                        class="truncate"
                                        :title="`${match.label}: ${match.text}`"
                                    >
                                        <span>{{ match.label }}: </span>
                                        <template
                                            v-for="(segment, segmentIndex) in highlightedSearchSegments(match.text)"
                                            :key="segmentIndex"
                                        >
                                            <mark
                                                v-if="segment.match"
                                                class="rounded bg-amber-200 px-0.5 font-bold text-amber-950"
                                            >{{ segment.text }}</mark>
                                            <span v-else>{{ segment.text }}</span>
                                        </template>
                                    </div>
                                </div>
                            </td>

                            <!-- 2. Khách Hàng (CLICK ĐỂ LỌC KH) -->
                            <td class="px-3.5 py-2.5">
                                <span
                                    class="cursor-pointer font-bold text-slate-900 hover:text-emerald-700 hover:underline"
                                    title="Bấm để lọc theo khách hàng này"
                                    @click.stop="filterByCustomer(getCustomerName(sale))"
                                >
                                    <template
                                        v-for="(segment, segmentIndex) in highlightedSearchSegments(getCustomerName(sale))"
                                        :key="segmentIndex"
                                    >
                                        <mark
                                            v-if="segment.match"
                                            class="rounded bg-amber-200 px-0.5 font-bold text-amber-950"
                                        >{{ segment.text }}</mark>
                                        <span v-else>{{ segment.text }}</span>
                                    </template>
                                </span>
                                <div class="mt-0.5 flex items-center gap-1 text-[10px] font-medium text-slate-400">
                                    <Phone class="h-3 w-3" />
                                    <template v-for="(segment, segmentIndex) in highlightedSearchSegments(getCustomerPhone(sale))" :key="segmentIndex">
                                        <mark v-if="segment.match" class="rounded bg-amber-200 px-0.5 font-bold text-amber-950">{{ segment.text }}</mark>
                                        <span v-else>{{ segment.text }}</span>
                                    </template>
                                </div>
                            </td>

                            <!-- 3. Thu ngân (CLICK ĐỂ LỌC THU NGÂN) -->
                            <td class="px-3.5 py-2.5 text-slate-600">
                                <span
                                    class="cursor-pointer hover:text-emerald-700 hover:underline"
                                    title="Bấm để lọc theo thu ngân này"
                                    @click.stop="filterByCashier(sale.user?.name)"
                                >
                                    <template
                                        v-for="(segment, segmentIndex) in highlightedSearchSegments(sale.user?.name || '-')"
                                        :key="segmentIndex"
                                    >
                                        <mark
                                            v-if="segment.match"
                                            class="rounded bg-amber-200 px-0.5 font-bold text-amber-950"
                                        >{{ segment.text }}</mark>
                                        <span v-else>{{ segment.text }}</span>
                                    </template>
                                </span>
                            </td>

                            <!-- Tổng tiền (CỠ CHỮ TO ĐẬM) -->
                            <td class="px-3.5 py-2.5 text-right">
                                <div class="text-sm font-black text-slate-900">{{ formatMoney(saleTotal(sale)) }}</div>
                                <div v-if="saleHasDebt(sale)" class="mt-0.5 text-[10px] font-bold text-rose-500">Nợ: {{ formatMoney(saleDebtAmount(sale)) }}</div>
                            </td>

                            <!-- 4. Trạng thái (CLICK ĐỂ LỌC TRẠNG THÁI) -->
                            <td class="px-3.5 py-2.5 text-center">
                                <span
                                    v-if="sale.status === 'cancelled'"
                                    class="inline-flex cursor-pointer items-center gap-1 rounded-full border border-rose-200 bg-rose-50 px-2.5 py-0.5 text-[10px] font-bold text-rose-600 hover:bg-rose-100"
                                    title="Bấm để lọc các đơn đã hủy"
                                    @click.stop="filterByStatus('cancelled')"
                                >
                                    <XCircle :size="12" /> Đã hủy
                                </span>
                                <span
                                    v-else-if="saleHasDebt(sale)"
                                    class="inline-flex cursor-pointer items-center gap-1 rounded-full border border-amber-200 bg-amber-50 px-2.5 py-0.5 text-[10px] font-bold text-amber-700 hover:bg-amber-100"
                                    title="Hóa đơn còn công nợ"
                                    @click.stop="filterByStatus('completed')"
                                >
                                    <Clock :size="12" /> Còn nợ
                                </span>
                                <span
                                    v-else
                                    class="inline-flex cursor-pointer items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 text-[10px] font-bold text-emerald-600 hover:bg-emerald-100"
                                    title="Bấm để lọc các đơn hoàn thành"
                                    @click.stop="filterByStatus('completed')"
                                >
                                    <CheckCircle2 :size="12" /> Đã thanh toán
                                </span>
                            </td>

                            <!-- Ngày tạo -->
                            <td class="px-3.5 py-2.5 text-slate-500 text-[11px]">
                                {{ formatDateTime(sale.created_at) }}
                            </td>

                            <!-- Thao tác -->
                            <td class="px-3.5 py-2.5 text-center">
                                <div class="flex items-center justify-center gap-1">
                                    <button
                                        v-if="canEditSales"
                                        type="button"
                                        class="rounded-lg p-2"
                                        :class="sale.status === 'cancelled' ? 'cursor-not-allowed text-slate-300 opacity-60' : 'text-amber-600 hover:bg-amber-50'"
                                        :disabled="sale.status === 'cancelled'"
                                        :title="sale.status === 'cancelled' ? 'Hóa đơn đã hủy, không thể sửa' : 'Sửa hóa đơn'"
                                        @click.stop="editSale = sale"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-lg p-2 text-emerald-700 hover:bg-emerald-50"
                                        title="In hóa đơn"
                                        @click.stop="openPrintWindow(sale.id)"
                                    >
                                        <Printer class="h-4 w-4" />
                                    </button>

                                    <button
                                        type="button"
                                        class="rounded-lg p-2 transition"
                                        :class="canCancelSales && sale.status !== 'cancelled' ? 'text-rose-600 hover:bg-rose-50' : 'cursor-not-allowed text-slate-300 opacity-60'"
                                        :disabled="!canCancelSales || sale.status === 'cancelled'"
                                        :title="sale.status === 'cancelled' ? 'Hóa đơn đã hủy' : (canCancelSales ? 'Trả hàng / Hủy hóa đơn & hoàn kho' : 'Bạn không có quyền trả hàng')"
                                        @click.stop="confirmCancel(sale)"
                                    >
                                        <Undo2 class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <!-- Bảng trống -->
                        <tr v-if="!salesData?.data || !salesData.data.length">
                            <td colspan="7" class="px-4 py-12 text-center">
                                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400 mb-3">
                                    <FileText :size="24" />
                                </div>
                                <p class="text-sm font-bold text-slate-700">Không tìm thấy hóa đơn nào</p>
                                <p class="text-xs text-slate-400 mt-1">Thử thay đổi bộ lọc hoặc từ khóa tìm kiếm</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                </div>

                <div class="divide-y divide-slate-100 md:hidden">
                    <article v-for="sale in sortedSalesData" :key="`mobile-${sale.id}`" class="cursor-pointer space-y-2.5 p-4 transition active:bg-slate-100" @click="openSaleDetail(sale)">
                        <div class="flex items-start justify-between gap-2">
                            <div class="text-sm font-bold text-slate-900">
                                <template v-for="(segment, segmentIndex) in highlightedSearchSegments(`#${sale.code || ''}`)" :key="segmentIndex">
                                    <mark v-if="segment.match" class="rounded bg-amber-200 px-0.5 font-bold text-amber-950">{{ segment.text }}</mark>
                                    <span v-else>{{ segment.text }}</span>
                                </template>
                            </div>
                            <span v-if="sale.status === 'cancelled'" class="inline-flex items-center gap-1 rounded-full border border-rose-200 bg-rose-50 px-2.5 py-1 text-[10px] font-bold text-rose-600"><XCircle class="h-3 w-3" /> Đã hủy</span>
                            <span v-else-if="saleHasDebt(sale)" class="inline-flex items-center gap-1 rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-[10px] font-bold text-amber-700"><Clock class="h-3 w-3" /> Còn nợ</span>
                            <span v-else class="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[10px] font-bold text-emerald-700"><CheckCircle2 class="h-3 w-3" /> Đã thanh toán</span>
                        </div>

                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="truncate text-sm font-bold text-slate-800">
                                    <template v-for="(segment, segmentIndex) in highlightedSearchSegments(getCustomerName(sale))" :key="segmentIndex">
                                        <mark v-if="segment.match" class="rounded bg-amber-200 px-0.5 font-bold text-amber-950">{{ segment.text }}</mark>
                                        <span v-else>{{ segment.text }}</span>
                                    </template>
                                </div>
                                <div class="mt-0.5 flex items-center gap-1 text-[11px] text-slate-400"><Phone class="h-3 w-3" /> {{ getCustomerPhone(sale) }} · {{ sale.user?.name || '-' }}</div>
                            </div>
                            <div class="shrink-0 text-right">
                                <div class="text-sm font-extrabold text-slate-900">{{ formatMoney(saleTotal(sale)) }}</div>
                                <div v-if="saleHasDebt(sale)" class="text-[10px] font-bold text-rose-500">Nợ: {{ formatMoney(saleDebtAmount(sale)) }}</div>
                            </div>
                        </div>

                        <div v-if="hasActiveSearch && saleSearchMatches(sale).length" class="space-y-0.5 text-[10px] font-medium text-slate-500">
                            <div v-for="match in saleSearchMatches(sale)" :key="`mobile-${match.label}-${match.text}`" class="truncate">
                                {{ match.label }}:
                                <template v-for="(segment, segmentIndex) in highlightedSearchSegments(match.text)" :key="segmentIndex">
                                    <mark v-if="segment.match" class="rounded bg-amber-200 px-0.5 font-bold text-amber-950">{{ segment.text }}</mark>
                                    <span v-else>{{ segment.text }}</span>
                                </template>
                            </div>
                        </div>

                        <div class="flex items-center justify-between pt-1 text-[11px] text-slate-400">
                            <span>{{ formatDateTime(sale.created_at) }}</span>
                            <span class="inline-flex items-center gap-0.5 font-semibold text-emerald-600">Xem chi tiết <ChevronRight class="h-3 w-3" /></span>
                        </div>
                    </article>

                    <div v-if="!salesData?.data?.length" class="p-10 text-center">
                        <FileText class="mx-auto mb-3 h-9 w-9 text-slate-300" />
                        <p class="text-sm font-bold text-slate-700">Không tìm thấy hóa đơn phù hợp</p>
                        <p class="mt-1 text-xs text-slate-400">Thử thay đổi từ khóa hoặc điều kiện lọc</p>
                    </div>
                </div>

                <PaginationBar :data="salesData" :filters="activeFilters" />
            </div>
        </DataPanel>
        

        <!-- MODAL CHI TIẾT HÓA ĐƠN -->
        <InvoiceDetailModal
            :show="Boolean(detailSale)"
            :invoice="detailSale"
            :can-cancel="canCancelSales && detailSale?.status !== 'cancelled'"
            can-print
            @close="closeSaleDetail"
            @cancel="confirmCancel"
            @print="openPrintWindow($event.id)"
        />
        <iframe
            v-if="printFrameUrl"
            :key="printFrameKey"
            :src="printFrameUrl"
            title="In hóa đơn"
            aria-hidden="true"
            tabindex="-1"
            class="pointer-events-none fixed left-0 top-0 h-px w-px border-0 opacity-0"
            @load="printReceiptFrame"
        />

        <SaleEditModal
            v-if="editSale"
            :sale="editSale"
            @close="editSale = null"
            @saved="onSaleEdited"
        />

        <!-- MODAL XÁC NHẬN HỦY HÓA ĐƠN -->
        <BaseModal
            v-if="showCancelModal"
            title="Xác nhận hủy hóa đơn"
            size="md"
            @close="showCancelModal = false"
        >
            <div class="space-y-4 p-1">
                <div class="flex items-start gap-3 p-3.5 bg-amber-50 rounded-2xl border border-amber-200">
                    <AlertTriangle class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" />
                    <div class="text-xs text-amber-800 leading-relaxed">
                        <strong>Lưu ý quan trọng:</strong> Khi hủy hóa đơn <strong>#{{ selectedSaleForCancel?.code }}</strong>, toàn bộ số lượng sản phẩm và mã IMEI/Serial liên quan sẽ được <strong>tự động hoàn trả về kho</strong>.
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">
                        Lý do hủy hóa đơn:
                    </label>

                    <textarea
                        v-model="cancelReason"
                        rows="3"
                        list="cancel-reasons-list"
                        placeholder="Nhập lý do hủy hoặc chọn từ các gợi ý bên dưới..."
                        class="w-full rounded-xl border border-slate-200 p-3 text-xs outline-none focus:border-rose-500 focus:ring-2 focus:ring-rose-100 transition"
                    ></textarea>

                    <datalist id="cancel-reasons-list">
                        <option v-for="(reason, index) in predefinedCancelReasons" :key="index" :value="reason" />
                    </datalist>

                    <div class="mt-2.5">
                        <div class="text-[11px] font-semibold text-slate-400 mb-1.5">
                            💡 Gợi ý lý do phổ biến (Bấm để chọn nhanh):
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            <button
                                v-for="(reason, idx) in predefinedCancelReasons"
                                :key="idx"
                                type="button"
                                @click="cancelReason = reason"
                                :class="[
                                    'px-2.5 py-1 rounded-lg text-xs font-medium transition border',
                                    cancelReason === reason
                                        ? 'bg-rose-50 border-rose-300 text-rose-700 font-bold'
                                        : 'bg-slate-50 border-slate-200 text-slate-600 hover:bg-slate-100 hover:text-slate-900'
                                ]"
                            >
                                {{ reason }}
                            </button>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
                    <button
                        type="button"
                        @click="showCancelModal = false"
                        class="px-4 py-2 rounded-xl border border-slate-200 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition"
                    >
                        Bỏ qua
                    </button>
                    <button
                        type="button"
                        @click="handleCancelSale"
                        :disabled="!cancelReason.trim()"
                        class="px-4 py-2 rounded-xl bg-rose-600 text-white text-xs font-bold hover:bg-rose-700 shadow-xs transition disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Xác nhận Hủy Đơn
                    </button>
                </div>
            </div>
        </BaseModal>
    </div>
</template>
