<script setup>
import { formatCurrency, formatDate as formatConfiguredDate } from '@/utils/format'
import { imageUrl } from '@/utils/imageUrl'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import StockImportFormModal from './StockImportFormModal.vue'
import ReplenishmentListModal from './ReplenishmentListModal.vue'
import api from '@/Services/api'
import PageHeader from '@/Components/UI/PageHeader.vue'
import ActionButton from '@/Components/UI/ActionButton.vue'
import DataPanel from '@/Components/UI/DataPanel.vue'
import BaseModal from '@/Components/UI/BaseModal.vue'
import PrintPreviewModal from '@/Components/PrintPreviewModal.vue'
import PaginationBar from '@/Components/UI/PaginationBar.vue'
import HighlightText from '@/Components/UI/HighlightText.vue'

import {
    ref,
    watch,
    onBeforeUnmount,
    computed,
} from 'vue'

import {
    router,
    usePage,
} from '@inertiajs/vue3'

import {
    Plus,
    Search,
    Truck,
    ClipboardList,
    Printer,
    SlidersHorizontal,
    RotateCcw,
    XCircle,
    Pencil,
    CheckCircle2,
    ChevronRight,
    FileText,
    Hash,
    UserRound,
    Wallet,
    X,
    LoaderCircle,
} from 'lucide-vue-next'


defineOptions({
    layout: AdminLayout,
})


const props = defineProps({

    imports: {
        type: Object,
        required: true,
    },

    categories: {
        type: Array,
        default: () => [],
    },


    suppliers: {
        type: Array,
        default: () => [],
    },

    filters: {
        type: Object,
        default: () => ({
            search: '',
            party_type: '',
            status: '',
            date_from: '',
            date_to: '',
        }),
    },

})
const page = usePage()
const canManageImports = computed(() => page.props.auth?.permissions?.includes('stock_imports.create') ?? false)


/*
|--------------------------------------------------------------------------
| MODAL
|--------------------------------------------------------------------------
*/

const showCreateModal = ref(false)
const importPartyType = ref('supplier')
const editingImport = ref(null)
const replenishmentDraft = ref(null)
const showReplenishmentModal = ref(false)
const detailImport = ref(null)
const printPreviewUrl = ref('')
const loadingDetail = ref(false)
const showCancelModal = ref(false)
const importToCancel = ref(null)
const cancelReason = ref('')


const openCreateModal = (partyType = 'supplier') => {
    editingImport.value = null
    replenishmentDraft.value = null
    importPartyType.value = partyType
    showCreateModal.value = true
}


const closeCreateModal = () => {
    showCreateModal.value = false
    editingImport.value = null
    replenishmentDraft.value = null
}


const handleCreated = () => {

    showCreateModal.value = false
    replenishmentDraft.value = null

    router.reload({
        only: ['imports'],
    })

}

const applyReplenishmentToImport = (draft) => {
    replenishmentDraft.value = draft
    editingImport.value = null
    importPartyType.value = 'supplier'
    showReplenishmentModal.value = false
    showCreateModal.value = true
}


/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

// Dữ liệu danh sách được giữ cục bộ để cập nhật tức thời (AJAX), không tải lại trang
const importsData = ref(props.imports)
const filtersData = ref(props.filters)

watch(() => props.imports, value => { importsData.value = value })
watch(() => props.filters, value => { filtersData.value = value }, { deep: true })

const search = ref(
    props.filters.search || ''
)
const partyTypeFilter = ref(props.filters.party_type || '')
const statusFilter = ref(props.filters.status || '')
const dateFrom = ref(props.filters.date_from || '')
const dateTo = ref(props.filters.date_to || '')
const showFilters = ref(Boolean(props.filters.party_type || props.filters.status || props.filters.date_from || props.filters.date_to))

const loadingImports = ref(false)
let searchTimer = null
let importsController = null
let importsRequestId = 0

const syncFilterUrl = (filters) => {
    const url = new URL(window.location.href)
    const values = {
        search: filters.search,
        party_type: filters.party_type,
        status: filters.status,
        date_from: filters.date_from,
        date_to: filters.date_to,
        per_page: filters.per_page,
        sort_by: filters.sort_by,
        sort_order: filters.sort_order,
    }
    Object.entries(values).forEach(([key, value]) => {
        if (value === '' || value === null || value === undefined) url.searchParams.delete(key)
        else url.searchParams.set(key, String(value))
    })
    url.searchParams.delete('page')
    window.history.replaceState(window.history.state, '', `${url.pathname}${url.search}${url.hash}`)
}

const requestImports = async (overrides = {}) => {
    clearTimeout(searchTimer)
    importsController?.abort()
    const requestId = ++importsRequestId
    importsController = new AbortController()
    loadingImports.value = true

    const params = {
        search: search.value.trim() || undefined,
        party_type: partyTypeFilter.value || undefined,
        status: statusFilter.value || undefined,
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
        per_page: filtersData.value?.per_page || undefined,
        sort_by: overrides.sort_by ?? filtersData.value?.sort_by ?? undefined,
        sort_order: overrides.sort_order ?? filtersData.value?.sort_order ?? undefined,
        page: 1,
    }

    try {
        const { data } = await api.get('/stock-import', { params, signal: importsController.signal })
        if (requestId !== importsRequestId) return
        importsData.value = data.imports
        filtersData.value = data.filters
        syncFilterUrl(data.filters || params)
    } catch (error) {
        if (error.name !== 'CanceledError' && requestId === importsRequestId) console.error(error)
    } finally {
        if (requestId === importsRequestId) loadingImports.value = false
    }
}

const submitSearch = () => requestImports()

const sortImports = (field) => {
    const sortOrder = filtersData.value?.sort_by === field && filtersData.value?.sort_order === 'asc' ? 'desc' : 'asc'
    requestImports({ sort_by: field, sort_order: sortOrder })
}

// Gõ liền mạch: chờ ngắn rồi tự lọc, kết quả cũ bị hủy khi có từ khóa mới
watch([search, partyTypeFilter, statusFilter, dateFrom, dateTo], () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(requestImports, 180)
})

onBeforeUnmount(() => {
    clearTimeout(searchTimer)
    importsController?.abort()
})

/*
|--------------------------------------------------------------------------
| SEARCH HIGHLIGHT (tô vàng phần khớp)
|--------------------------------------------------------------------------
*/
const hasActiveSearch = computed(() => search.value.trim().length > 0)

const foldText = (value) => Array.from(String(value || ''))
    .map(character => character
        .normalize('NFD')
        .replace(/\p{Diacritic}/gu, '')
        .replace(/đ/g, 'd')
        .replace(/Đ/g, 'D')
        .toLocaleLowerCase('vi-VN'))
    .join('')

const importPartyName = (item) => item.party_name || item.customer?.full_name || item.supplier?.name || '—'
const importPartyPhone = (item) => item.party_phone || item.customer?.phone || item.supplier?.phone || ''

// Chỉ hiện các dòng "khớp ở đâu" còn đúng với từ khóa đang gõ
const importSearchMatches = (item) => {
    const keyword = foldText(search.value.trim())
    if (!keyword) return []
    return (item.search_matches || [])
        .filter(match => foldText(match.text).includes(keyword))
        .slice(0, 3)
}


/*
|--------------------------------------------------------------------------
| FORMAT
|--------------------------------------------------------------------------
*/

const money = formatCurrency


const formatDate = (value) => value ? formatConfiguredDate(value) : '-'

const statusLabel = (status) => ({
    draft: 'Nháp',
    completed: 'Hoàn thành',
    pending: 'Chờ xử lý',
    cancelled: 'Đã hủy',
})[String(status || '').toLowerCase()] || status || 'Hoàn thành'

const statusClass = (status) => String(status || '').toLowerCase() === 'cancelled'
    ? 'border border-rose-200 bg-rose-50 text-rose-600'
    : String(status || '').toLowerCase() === 'pending'
        ? 'border border-amber-200 bg-amber-50 text-amber-700'
        : 'border border-emerald-200 bg-emerald-50 text-emerald-700'

const filterByStatus = (value) => {
    statusFilter.value = value
}

const attributeText = (attribute) => {
    if (attribute === null || attribute === undefined || attribute === '') {
        return ''
    }

    if (typeof attribute !== 'object') {
        return String(attribute)
    }

    return attribute.value ||
        attribute.label ||
        attribute.name ||
        attribute.title ||
        ''
}

const variantLabel = (variant) => {
    if (!variant) {
        return '-'
    }

    const attributes = Object.values(variant.attributes || {})
        .map(attributeText)
        .filter(Boolean)

    return attributes.join(' / ') ||
        variant.sku ||
        '-'
}

const hasExtraInfo = (imei) => {
    const extraInfo = imei?.extra_info || {}

    return Boolean(
        extraInfo.note ||
        extraInfo.account ||
        extraInfo.password ||
        extraInfo.image_path
    )
}

const extraInfoImageUrl = (imei) =>
    imageUrl(imei?.extra_info?.image_path)


/*
|--------------------------------------------------------------------------
| DETAIL MODAL HELPERS
|--------------------------------------------------------------------------
*/
const isCustomerImport = (stockImport) => stockImport?.party_type === 'customer'

const detailPartyName = (d) => d.party_name || d.customer?.full_name || d.supplier?.name || '-'
const detailPartyPhone = (d) => d.party_phone || d.customer?.phone || d.supplier?.phone || ''

// Mua từ khách lẻ: thanh toán ngay, không phát sinh công nợ
const detailPaid = (d) => isCustomerImport(d) ? Number(d.grand_total || 0) : Number(d.paid_amount || 0)
const detailDebt = (d) => isCustomerImport(d) ? 0 : Math.max(0, Number(d.grand_total || 0) - Number(d.paid_amount || 0))

const paymentMethodLabel = (method) => ({ cash: 'Tiền mặt', bank: 'Chuyển khoản', card: 'Thẻ' })[method] || ''

const warrantyLabel = (item) => {
    const value = Number(item?.supplier_warranty_value || 0)
    if (!value) return ''
    const unit = String(item.supplier_warranty_unit || 'months').toLowerCase()
    const label = unit.startsWith('day') ? 'ngày' : unit.startsWith('year') ? 'năm' : 'tháng'
    return `${value} ${label}`
}

const itemKindLabel = (item) => {
    if (item.extra_info?.is_used) return 'Đã qua sử dụng'
    if (item.product?.manage_stock_by_serial) return 'Quản lý IMEI/Serial'
    return 'Sản phẩm thường'
}

const handleDetailEsc = (event) => {
    if (event.key !== 'Escape') return
    // Không đóng khi đang có modal khác mở phía trên
    if (showCancelModal.value || showCreateModal.value || printPreviewUrl.value) return
    detailImport.value = null
}

watch(detailImport, (value, oldValue) => {
    if (value && !oldValue) window.addEventListener('keydown', handleDetailEsc)
    if (!value && oldValue) window.removeEventListener('keydown', handleDetailEsc)
})

onBeforeUnmount(() => window.removeEventListener('keydown', handleDetailEsc))

const itemImportImeis = (item) =>
    item.import_imeis || []


/*
|--------------------------------------------------------------------------
| DETAIL
|--------------------------------------------------------------------------
*/

const showImport = (id) => {
    loadingDetail.value = true

    api.get(`/stock-import/${id}`)
        .then((response) => {
            detailImport.value = response.data
        })
        .catch(() => {
            detailImport.value = null
        })
        .finally(() => {
            loadingDetail.value = false
        })

}

const requestCancelImport = (stockImport) => {
    importToCancel.value = stockImport
    cancelReason.value = ''
    showCancelModal.value = true
}

const editImport = async (stockImport) => {
    if (!stockImport || stockImport.status === 'cancelled') return

    // Dữ liệu từ bảng chỉ là bản rút gọn; luôn lấy phiếu đầy đủ để biểu mẫu có đủ dòng hàng/IMEI.
    loadingDetail.value = true
    try {
        const response = stockImport.items ? { data: stockImport } : await api.get(`/stock-import/${stockImport.id}`)
        editingImport.value = response.data
        replenishmentDraft.value = null
        importPartyType.value = response.data.party_type || 'supplier'
        detailImport.value = null
        showCreateModal.value = true
    } finally {
        loadingDetail.value = false
    }
}

const cancelImport = () => {
    if (!importToCancel.value || !cancelReason.value.trim()) return
    router.post(`/stock-import/${importToCancel.value.id}/cancel`, { reason: cancelReason.value.trim() }, {
        preserveScroll: true,
        onSuccess: () => {
            showCancelModal.value = false
            detailImport.value = null
            importToCancel.value = null
            router.reload({ only: ['imports'] })
        },
    })
}

const openImportFromGlobalSearch = () => {
    const url = new URL(window.location.href)
    const importId = Number(url.searchParams.get('open_import'))
    if (!importId) return
    showImport(importId)
    url.searchParams.delete('open_import')
    window.history.replaceState(window.history.state, '', url)
}

watch(() => page.url, openImportFromGlobalSearch, { immediate: true })

</script>


<template>

    <div class="text-slate-800">

    <!-- HEADER -->

    <!-- CONTENT -->

    <div class="space-y-3">
        <PageHeader title="Phiếu nhập hàng" />

        <DataPanel detached>

            <!-- SEARCH -->

            <div
                class="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between sm:px-5"
            >

                <div class="min-w-0 flex-1 sm:max-w-lg">
                    <div class="relative">
                        <Search :size="18" class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                        <input
                            v-model="search"
                            type="text"
                            autocomplete="off"
                            placeholder="Tìm mã phiếu, nhà cung cấp / khách, SĐT, sản phẩm, IMEI/Serial..."
                            @keyup.enter="submitSearch"
                            class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-10 text-sm font-medium text-slate-800 outline-none transition focus:border-emerald-500 focus:bg-white focus:ring-4 focus:ring-emerald-100"
                        />
                        <LoaderCircle v-if="loadingImports" class="pointer-events-none absolute right-3.5 top-1/2 h-4 w-4 -translate-y-1/2 animate-spin text-emerald-600" />
                        <button v-else-if="search" type="button" class="absolute right-3 top-1/2 flex h-5 w-5 -translate-y-1/2 items-center justify-center rounded-full bg-slate-200 text-slate-500 transition hover:bg-slate-300" title="Xóa từ khóa" aria-label="Xóa tìm kiếm" @click="search = ''"><X class="h-3 w-3" /></button>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2 sm:shrink-0 sm:justify-end">
                    <button
                        type="button"
                        :class="showFilters ? 'border-blue-300 bg-blue-50 text-blue-700' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'"
                        class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border px-3 py-2 text-sm font-semibold transition"
                        @click="showFilters = !showFilters"
                    >
                        <SlidersHorizontal :size="16" />
                        Bộ lọc
                        <span v-if="[partyTypeFilter, statusFilter, dateFrom, dateTo].filter(Boolean).length" class="rounded-full bg-blue-100 px-1.5 text-[11px] text-blue-700">{{ [partyTypeFilter, statusFilter, dateFrom, dateTo].filter(Boolean).length }}</span>
                    </button>
                    <button
                        type="button"
                        class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-800 transition hover:border-amber-300 hover:bg-amber-100"
                        @click="showReplenishmentModal = true"
                    >
                        <ClipboardList :size="17" />
                        Lập DS lấy hàng
                    </button>
                    <ActionButton @click="openCreateModal('supplier')">
                        <Plus :size="18" />
                        Tạo đơn nhập
                    </ActionButton>
                </div>

            </div>

            <div v-if="showFilters" class="grid gap-3 border-b border-slate-200 bg-slate-50/70 px-4 py-3 sm:grid-cols-2 sm:px-5 lg:grid-cols-5">
                <label class="space-y-1 text-xs font-semibold text-slate-600">
                    <span>Loại phiếu</span>
                    <select v-model="partyTypeFilter" class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm font-normal text-slate-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                        <option value="">Tất cả</option>
                        <option value="supplier">Nhà cung cấp</option>
                        <option value="customer">Mua từ khách lẻ</option>
                    </select>
                </label>
                <label class="space-y-1 text-xs font-semibold text-slate-600">
                    <span>Trạng thái</span>
                    <select v-model="statusFilter" class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm font-normal text-slate-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                        <option value="">Tất cả</option>
                        <option value="completed">Hoàn thành</option>
                        <option value="draft">Nháp</option>
                        <option value="cancelled">Đã hủy</option>
                    </select>
                </label>
                <label class="space-y-1 text-xs font-semibold text-slate-600">
                    <span>Từ ngày</span>
                    <input v-model="dateFrom" type="date" class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm font-normal text-slate-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100" />
                </label>
                <label class="space-y-1 text-xs font-semibold text-slate-600">
                    <span>Đến ngày</span>
                    <input v-model="dateTo" type="date" class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm font-normal text-slate-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100" />
                </label>
                <div class="flex items-end">
                    <button type="button" class="inline-flex h-10 items-center gap-2 rounded-lg px-3 text-sm font-semibold text-slate-500 transition hover:bg-white hover:text-slate-800" @click="partyTypeFilter = ''; statusFilter = ''; dateFrom = ''; dateTo = ''; search = ''">
                        <RotateCcw :size="15" /> Xóa bộ lọc
                    </button>
                </div>
            </div>


            <!-- TABLE -->

            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="hidden overflow-x-auto md:block">

                <table class="app-data-table w-full text-sm">

                    <thead
                        class="
                            bg-slate-50
                            text-xs
                            font-semibold
                            uppercase
                            text-slate-500
                        "
                    >

                        <tr>

                            <th class="px-5 py-3 text-left"><button type="button" class="hover:text-emerald-700" @click="sortImports('code')">Mã đơn ↕</button></th>

                            <th class="px-3 py-3 text-left">
                                Nhà cung cấp / khách lẻ
                            </th>

                            <th class="px-3 py-3 text-center"><button type="button" class="hover:text-emerald-700" @click="sortImports('import_date')">Ngày nhập ↕</button></th>

                            <th class="px-3 py-3 text-center"><button type="button" class="hover:text-emerald-700" @click="sortImports('items_count')">Số dòng ↕</button></th>

                            <th class="px-3 py-3 text-right"><button type="button" class="hover:text-emerald-700" @click="sortImports('grand_total')">Tổng tiền ↕</button></th>

                            <th class="px-3 py-3 text-center"><button type="button" class="hover:text-emerald-700" @click="sortImports('status')">Trạng thái ↕</button></th>

                            <th class="px-3 py-3 text-center">Thao tác</th>

                        </tr>

                    </thead>


                    <tbody>

                        <tr
                            v-for="item in importsData.data"
                            :key="item.id"
                            tabindex="0"
                            class="cursor-pointer border-t border-slate-100 transition-colors hover:bg-emerald-50/70"
                            title="Bấm vào dòng để xem chi tiết"
                            @click="showImport(item.id)"
                            @keydown.enter.self="showImport(item.id)"
                        >

                            <!-- CODE -->

                            <td class="px-5 py-4">

                                <div
                                    class="
                                        font-semibold
                                        text-slate-800
                                    "
                                >
                                    <HighlightText :text="item.code" :keyword="search" />
                                </div>

                                <div v-if="hasActiveSearch && importSearchMatches(item).length" class="mt-1 max-w-[280px] space-y-0.5 text-[10px] font-medium text-slate-500">
                                    <div v-for="match in importSearchMatches(item)" :key="`${match.label}-${match.text}`" class="truncate" :title="`${match.label}: ${match.text}`">
                                        <span>{{ match.label }}: </span><HighlightText :text="match.text" :keyword="search" />
                                    </div>
                                </div>

                            </td>


                            <!-- SUPPLIER -->

                            <td class="px-3 py-4">

                                <div
                                    class="
                                        font-medium
                                        text-slate-800
                                    "
                                >
                                    <HighlightText :text="importPartyName(item)" :keyword="search" />
                                </div>

                                <div v-if="item.party_phone || item.customer?.phone || item.supplier?.phone" class="mt-0.5 text-xs text-slate-500">
                                    <HighlightText :text="importPartyPhone(item)" :keyword="search" />
                                </div>

                            </td>


                            <!-- DATE -->

                            <td
                                class="
                                    px-3
                                    py-4
                                    text-center
                                    text-slate-600
                                "
                            >
                                {{ formatDate(item.import_date) }}
                            </td>


                            <!-- ITEMS -->

                            <td
                                class="
                                    px-3
                                    py-4
                                    text-center
                                "
                            >
                                {{ item.items_count }}
                            </td>


                            <!-- TOTAL -->

                            <td
                                class="
                                    px-3
                                    py-4
                                    text-right
                                    font-semibold
                                    text-slate-900
                                "
                            >
                                {{ money(item.grand_total) }}
                            </td>


                            <!-- STATUS -->

                            <td class="px-3 py-4 text-center">

                                <span
                                    class="inline-flex cursor-pointer items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold transition hover:brightness-95"
                                    :class="statusClass(item.status)"
                                    :title="`Lọc: ${statusLabel(item.status)}`"
                                    @click.stop="filterByStatus(item.status || 'completed')"
                                >
                                    <XCircle v-if="item.status === 'cancelled'" class="h-3.5 w-3.5" />
                                    <CheckCircle2 v-else class="h-3.5 w-3.5" />
                                    {{ statusLabel(item.status) }}
                                </span>

                            </td>

                            <td class="px-3 py-4 text-center">
                                <div class="flex items-center justify-center gap-1">
                                    <button
                                        type="button"
                                        class="rounded-lg p-2 transition"
                                        :class="canManageImports && item.status !== 'cancelled' ? 'text-amber-600 hover:bg-amber-50' : 'cursor-not-allowed text-slate-300 opacity-60'"
                                        :disabled="!canManageImports || item.status === 'cancelled'"
                                        :title="item.status === 'cancelled' ? 'Phiếu nhập đã hủy, không thể sửa' : (canManageImports ? 'Sửa phiếu nhập' : 'Bạn không có quyền sửa phiếu nhập')"
                                        @click.stop="editImport(item)"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-lg p-2 transition"
                                        :class="canManageImports && item.status !== 'cancelled' ? 'text-rose-600 hover:bg-rose-50' : 'cursor-not-allowed text-slate-300 opacity-60'"
                                        :disabled="!canManageImports || item.status === 'cancelled'"
                                        :title="item.status === 'cancelled' ? 'Phiếu nhập đã hủy' : (canManageImports ? 'Hủy phiếu nhập và hoàn tác tồn kho' : 'Bạn không có quyền hủy phiếu nhập')"
                                        @click.stop="requestCancelImport(item)"
                                    >
                                        <XCircle class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>



                        </tr>


                        <!-- EMPTY -->

                        <tr
                            v-if="!importsData.data?.length"
                        >

                            <td
                                colspan="7"
                                class="
                                    px-5
                                    py-16
                                    text-center
                                    text-slate-400
                                "
                            >
                                <div class="mx-auto flex max-w-sm flex-col items-center gap-2">
                                    <Search v-if="search" :size="22" class="text-slate-300" />
                                    <span>{{ search ? `Không tìm thấy phiếu nhập phù hợp với “${search}”` : 'Chưa có đơn nhập hàng' }}</span>
                                    <button v-if="search" type="button" class="text-xs font-semibold text-emerald-700 hover:text-emerald-900" @click="search = ''">Xóa từ khóa tìm kiếm</button>
                                </div>
                            </td>

                        </tr>

                    </tbody>

                </table>

            </div>

            <div class="divide-y divide-slate-200 md:hidden">
                <article v-for="item in importsData.data" :key="`mobile-import-${item.id}`" class="cursor-pointer space-y-2.5 p-3.5 active:bg-slate-50" @click="showImport(item.id)">
                    <div class="flex items-start justify-between gap-3"><div class="min-w-0"><div class="font-bold text-slate-900"><HighlightText :text="item.code" :keyword="search" /></div><div class="mt-0.5 truncate text-xs text-slate-500"><HighlightText :text="importPartyName(item)" :keyword="search" /></div><div class="text-xs text-slate-500"><HighlightText :text="importPartyPhone(item)" :keyword="search" /></div><div v-if="hasActiveSearch && importSearchMatches(item).length" class="mt-1 space-y-0.5 text-[10px] font-medium text-slate-500"><div v-for="match in importSearchMatches(item)" :key="`${match.label}-${match.text}`" class="truncate"><span>{{ match.label }}: </span><HighlightText :text="match.text" :keyword="search" /></div></div></div><span class="shrink-0 rounded-full px-2 py-1 text-[11px] font-semibold" :class="statusClass(item.status)">{{ statusLabel(item.status) }}</span></div>
                    <div class="flex items-center justify-between border-t border-slate-100 pt-2 text-xs"><span class="text-slate-500">{{ formatDate(item.import_date) }} · {{ item.items_count }} dòng</span><span class="font-bold text-slate-900">{{ money(item.grand_total) }}</span></div>
                    <div class="flex items-center justify-between pt-0.5"><span class="inline-flex items-center gap-0.5 text-[11px] font-semibold text-emerald-700">Xem chi tiết <ChevronRight class="h-3 w-3" /></span><div class="flex items-center gap-1"><button type="button" :disabled="!canManageImports || item.status === 'cancelled'" class="rounded-lg p-2 transition" :class="canManageImports && item.status !== 'cancelled' ? 'text-amber-600 hover:bg-amber-50' : 'text-slate-300 opacity-60'" title="Sửa phiếu nhập" @click.stop="editImport(item)"><Pencil class="h-4 w-4" /></button><button type="button" :disabled="!canManageImports || item.status === 'cancelled'" class="rounded-lg p-2 transition" :class="canManageImports && item.status !== 'cancelled' ? 'text-rose-600 hover:bg-rose-50' : 'text-slate-300 opacity-60'" title="Hủy phiếu nhập" @click.stop="requestCancelImport(item)"><XCircle class="h-4 w-4" /></button></div></div>
                </article>
                <div v-if="!importsData.data?.length" class="p-8 text-center text-sm text-slate-500">{{ search ? `Không tìm thấy phiếu nhập phù hợp với “${search}”` : 'Chưa có đơn nhập hàng' }}</div>
            </div>
            <PaginationBar :data="importsData" :filters="filtersData" />
            </div>

        </DataPanel>

    </div>


    <!-- ========================================================= -->
    <!-- CREATE MODAL -->
    <!-- ========================================================= -->

    <StockImportFormModal
        v-if="showCreateModal"
        :initial-party-type="importPartyType"
        :initial-import="editingImport"
        :initial-replenishment="replenishmentDraft"
        :categories="props.categories"
        :suppliers="props.suppliers"
        @close="closeCreateModal"
        @created="handleCreated"
    />

    <ReplenishmentListModal
        v-if="showReplenishmentModal"
        :suppliers="props.suppliers"
        @close="showReplenishmentModal = false"
        @apply="applyReplenishmentToImport"
    />

    <Teleport to="body">
        <div v-if="detailImport" class="fixed inset-0 z-[1000] flex items-center justify-center bg-slate-900/50 p-3 sm:p-4">
            <div class="absolute inset-0" @click="detailImport = null"></div>

            <section role="dialog" aria-modal="true" class="relative flex max-h-[95dvh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">

                <!-- HEADER -->
                <div class="flex shrink-0 items-center justify-between border-b border-slate-100 bg-white px-5 py-4 sm:px-6">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                            <FileText class="h-5 w-5" />
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-xl font-bold text-slate-800">Chi tiết đơn nhập</h2>
                            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="statusClass(detailImport.status)">
                                <span class="h-1.5 w-1.5 rounded-full bg-current"></span>{{ statusLabel(detailImport.status) }}
                            </span>
                        </div>
                    </div>
                    <button type="button" title="Đóng" class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" @click="detailImport = null">
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <!-- BODY -->
                <div class="min-h-0 flex-1 space-y-5 overflow-y-auto bg-slate-50/60 p-4 sm:p-6">

                    <!-- 4 thẻ thông tin -->
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-4">
                        <div class="rounded-xl border border-slate-200/80 bg-white p-3.5 shadow-sm">
                            <div class="mb-1 flex items-center gap-1 text-xs font-medium uppercase tracking-wider text-slate-400"><Hash class="h-3 w-3" /> Mã đơn nhập</div>
                            <div class="break-all font-bold text-slate-800">{{ detailImport.code }}</div>
                            <div class="mt-0.5 text-xs text-slate-500">{{ formatDate(detailImport.import_date) }}</div>
                        </div>

                        <div class="rounded-xl border border-slate-200/80 bg-white p-3.5 shadow-sm">
                            <div class="mb-1 flex items-center gap-1 text-xs font-medium uppercase tracking-wider text-slate-400"><Truck class="h-3 w-3" /> {{ isCustomerImport(detailImport) ? 'Khách lẻ' : 'Nhà cung cấp' }}</div>
                            <div class="font-bold text-slate-800">{{ detailPartyName(detailImport) }}</div>
                            <div class="mt-0.5 text-xs text-slate-500">{{ detailPartyPhone(detailImport) || '-' }}</div>
                            <div v-if="detailImport.party_cccd || detailImport.customer?.cccd" class="mt-0.5 text-xs text-slate-500">CCCD: {{ detailImport.party_cccd || detailImport.customer?.cccd }}</div>
                            <div v-if="detailImport.party_address || detailImport.customer?.address" class="mt-0.5 text-xs text-slate-500">{{ detailImport.party_address || detailImport.customer?.address }}</div>
                        </div>

                        <div class="rounded-xl border border-slate-200/80 bg-white p-3.5 shadow-sm">
                            <div class="mb-1 flex items-center gap-1 text-xs font-medium uppercase tracking-wider text-slate-400"><UserRound class="h-3 w-3" /> Người tạo</div>
                            <div class="font-bold text-slate-800">{{ detailImport.user?.name || '-' }}</div>
                            <div v-if="paymentMethodLabel(detailImport.payment_method) && !isCustomerImport(detailImport)" class="mt-0.5 text-xs text-slate-500">Thanh toán: {{ paymentMethodLabel(detailImport.payment_method) }}</div>
                        </div>

                        <div class="rounded-xl border border-indigo-100 bg-indigo-50/40 p-3.5 shadow-sm">
                            <div class="mb-1 flex items-center gap-1 text-xs font-medium uppercase tracking-wider text-indigo-500"><Wallet class="h-3 w-3" /> Tổng thanh toán</div>
                            <div class="text-lg font-bold text-indigo-600">{{ money(detailImport.grand_total) }}</div>
                            <div class="mt-0.5 text-xs font-medium" :class="detailDebt(detailImport) > 0 ? 'text-orange-600' : 'text-emerald-600'">
                                {{ detailDebt(detailImport) > 0 ? `Còn nợ ${money(detailDebt(detailImport))}` : 'Đã thanh toán đủ' }}
                            </div>
                        </div>
                    </div>

                    <!-- Bảng hàng hóa -->
                    <div class="overflow-x-auto rounded-xl border border-slate-200/80 bg-white shadow-sm">
                        <table class="w-full min-w-[820px] border-collapse text-left">
                            <thead>
                                <tr class="border-b border-slate-200 bg-slate-50/80 text-xs font-semibold uppercase text-slate-500">
                                    <th class="w-12 px-4 py-3 text-center">STT</th>
                                    <th class="px-4 py-3">Sản phẩm</th>
                                    <th class="px-4 py-3">Loại</th>
                                    <th class="px-4 py-3 text-center">SL</th>
                                    <th class="px-4 py-3 text-right">Đơn giá</th>
                                    <th class="px-4 py-3 text-right">Thành tiền</th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-slate-100 text-sm text-slate-700">
                                <template v-for="(item, index) in detailImport.items" :key="item.id">
                                    <tr class="transition hover:bg-slate-50/50">
                                        <td class="px-4 py-3.5 text-center font-medium text-slate-400">{{ index + 1 }}</td>
                                        <td class="px-4 py-3.5">
                                            <div class="font-semibold text-slate-800">{{ item.product?.name || '-' }}</div>
                                            <div class="text-xs text-slate-500">SKU: {{ item.product?.sku || '-' }}</div>
                                            <div v-if="item.variant" class="text-xs font-semibold text-indigo-700">{{ variantLabel(item.variant) }}</div>
                                            <div v-if="item.has_vat || warrantyLabel(item)" class="mt-1 flex flex-wrap items-center gap-2">
                                                <span v-if="item.has_vat" class="inline-flex items-center rounded border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-600">Có VAT</span>
                                                <span v-if="warrantyLabel(item)" class="inline-flex items-center rounded border border-blue-200 bg-blue-50 px-2 py-0.5 text-[11px] font-medium text-blue-600">BH NCC: {{ warrantyLabel(item) }}</span>
                                            </div>
                                            <div v-if="item.extra_info?.is_used" class="mt-1 space-y-0.5 text-[11px] text-amber-800">
                                                <div v-if="item.extra_info.account">Tài khoản: {{ item.extra_info.account }}</div>
                                                <div v-if="item.extra_info.password">Mật khẩu: {{ item.extra_info.password }}</div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3.5">
                                            <span class="inline-flex items-center rounded-md px-2.5 py-1 text-xs font-medium" :class="item.extra_info?.is_used ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-700'">{{ itemKindLabel(item) }}</span>
                                        </td>
                                        <td class="px-4 py-3.5 text-center font-semibold">
                                            <div>{{ item.quantity }} {{ item.unit_name || item.unit?.short_name || '' }}</div>
                                            <div v-if="Number(item.conversion_factor || 1) > 1" class="mt-0.5 text-[11px] font-medium text-indigo-600">
                                                = {{ item.base_quantity }} {{ item.product?.unit?.short_name || item.product?.unit?.name || 'đơn vị bán' }}
                                            </div>
                                        </td>
                                        <td class="px-4 py-3.5 text-right">{{ money(item.cost_price) }}</td>
                                        <td class="px-4 py-3.5 text-right font-bold text-slate-900">{{ money(Number(item.quantity || 0) * Number(item.cost_price || 0)) }}</td>
                                    </tr>

                                    <tr v-if="itemImportImeis(item).length">
                                        <td colspan="6" class="bg-slate-50/70 px-4 py-3">
                                            <div class="mb-2 text-xs font-bold uppercase text-slate-500">IMEI / Serial đã nhập</div>

                                            <div class="grid gap-2 md:grid-cols-2">
                                                <div v-for="history in itemImportImeis(item)" :key="history.id" class="rounded-xl border border-slate-200 bg-white p-3 text-xs">
                                                    <div class="flex flex-wrap items-start justify-between gap-2">
                                                        <div>
                                                            <div class="font-mono text-sm font-bold text-slate-900">{{ history.product_imei?.imei || history.imei }}</div>
                                                            <div v-if="history.product_imei?.serial" class="mt-0.5 font-mono text-slate-500">Serial: {{ history.product_imei.serial }}</div>
                                                        </div>
                                                        <span class="rounded-full bg-emerald-50 px-2 py-0.5 font-bold text-emerald-700">{{ history.product_imei?.status || 'in_stock' }}</span>
                                                    </div>

                                                    <div class="mt-2 grid gap-2 sm:grid-cols-3">
                                                        <div>
                                                            <div class="font-bold uppercase text-slate-400">Giá nhập</div>
                                                            <div class="font-semibold text-slate-800">{{ money(history.cost_price) }}</div>
                                                        </div>
                                                        <div>
                                                            <div class="font-bold uppercase text-slate-400">Giá bán</div>
                                                            <div class="font-semibold text-slate-800">{{ money(history.sell_price) }}</div>
                                                        </div>
                                                        <div>
                                                            <div class="font-bold uppercase text-slate-400">BH NCC</div>
                                                            <div class="font-semibold text-slate-800">{{ formatDate(history.product_imei?.warranty_expired_at || history.meta?.warranty_expired_at) }}</div>
                                                        </div>
                                                    </div>

                                                    <div v-if="hasExtraInfo(history.product_imei)" class="mt-2 space-y-0.5 rounded-lg bg-slate-50 p-2 text-slate-600">
                                                        <div v-if="history.product_imei.extra_info?.note">{{ history.product_imei.extra_info.note }}</div>
                                                        <div v-if="history.product_imei.extra_info?.account">Tài khoản: {{ history.product_imei.extra_info.account }}</div>
                                                        <div v-if="history.product_imei.extra_info?.password">Mật khẩu: {{ history.product_imei.extra_info.password }}</div>
                                                        <a v-if="extraInfoImageUrl(history.product_imei)" :href="extraInfoImageUrl(history.product_imei)" target="_blank" class="inline-flex font-semibold text-blue-600 hover:text-blue-700">Xem ảnh thiết bị</a>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <!-- Ghi chú & tổng kết -->
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-12">
                        <div class="flex flex-col justify-between rounded-xl border border-slate-200/80 bg-white p-4 shadow-sm md:col-span-6">
                            <div>
                                <div class="mb-2 flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-slate-500"><Pencil class="h-3.5 w-3.5" /> Ghi chú đơn nhập</div>
                                <div class="rounded-lg border border-slate-100 bg-slate-50 p-3 text-sm" :class="detailImport.note ? 'text-slate-600' : 'italic text-slate-400'">
                                    {{ detailImport.note || 'Không có ghi chú.' }}
                                </div>
                            </div>
                            <div v-if="detailImport.payment_reference" class="mt-4 border-t border-slate-100 pt-3 text-xs text-slate-400">
                                Nội dung chuyển khoản: <span class="select-all font-mono text-slate-500">{{ detailImport.payment_reference }}</span>
                            </div>
                        </div>

                        <div class="space-y-2.5 rounded-xl border border-slate-200/80 bg-white p-4 text-sm shadow-sm md:col-span-6">
                            <div class="flex justify-between text-slate-600"><span>Tiền hàng:</span><span class="font-medium text-slate-800">{{ money(detailImport.total_amount) }}</span></div>
                            <div class="flex justify-between text-slate-600"><span>Giảm giá:</span><span class="font-medium text-red-600">-{{ money(detailImport.discount) }}</span></div>
                            <div class="flex justify-between text-slate-600"><span>Phí khác / Vận chuyển:</span><span class="font-medium text-slate-800">{{ money(detailImport.extra_fee) }}</span></div>
                            <div class="flex items-center justify-between border-t border-slate-100 pt-2.5 text-base">
                                <span class="font-bold text-slate-800">Tổng thanh toán:</span>
                                <span class="text-lg font-bold text-indigo-600">{{ money(detailImport.grand_total) }}</span>
                            </div>
                            <div class="flex flex-wrap justify-between gap-x-4 gap-y-1 rounded-lg bg-slate-50 p-2 text-xs text-slate-600">
                                <span>{{ isCustomerImport(detailImport) ? 'Đã trả khách' : 'Đã trả NCC' }}: <strong class="text-emerald-600">{{ money(detailPaid(detailImport)) }}</strong></span>
                                <span v-if="Number(detailImport.old_debt_paid || 0) > 0">Trả nợ cũ kèm theo: <strong class="text-amber-600">{{ money(detailImport.old_debt_paid) }}</strong></span>
                                <span>Còn nợ: <strong :class="detailDebt(detailImport) > 0 ? 'text-orange-600' : 'text-slate-700'">{{ money(detailDebt(detailImport)) }}</strong></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FOOTER -->
                <div class="flex shrink-0 flex-wrap items-center justify-between gap-2 border-t border-slate-200 bg-white px-5 py-3.5 sm:px-6">
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            v-if="canManageImports && detailImport.status !== 'cancelled'"
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-xl border border-red-200 bg-red-50 px-4 py-2 text-sm font-medium text-red-600 transition hover:bg-red-100"
                            @click="requestCancelImport(detailImport)"
                        >
                            <XCircle class="h-4 w-4" /> Hủy phiếu
                        </button>
                        <button
                            v-if="canManageImports && detailImport.status !== 'cancelled'"
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-medium text-amber-700 transition hover:bg-amber-100"
                            @click="editImport(detailImport)"
                        >
                            <Pencil class="h-4 w-4" /> Sửa phiếu
                        </button>
                    </div>

                    <div class="flex items-center gap-3">
                        <button type="button" class="rounded-xl bg-slate-100 px-5 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-200" @click="detailImport = null">Đóng</button>
                        <button
                            v-if="detailImport.party_type === 'customer'"
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-5 py-2 text-sm font-medium text-white shadow-md shadow-indigo-200 transition hover:bg-indigo-700"
                            @click="printPreviewUrl = `/stock-import/${detailImport.id}/contract`"
                        >
                            <Printer class="h-4 w-4" /> In hợp đồng mua bán
                        </button>
                    </div>
                </div>
            </section>
        </div>
    </Teleport>
    <PrintPreviewModal :show="Boolean(printPreviewUrl)" title="Xem trước hợp đồng mua bán" :src="printPreviewUrl" @close="printPreviewUrl = ''" />

    <BaseModal v-if="showCancelModal" title="Hủy phiếu nhập" size="sm" @close="showCancelModal = false">
        <div class="space-y-3">
            <p class="text-sm text-slate-600">Tồn kho và công nợ của phiếu sẽ được hoàn tác. Chỉ hủy được khi hàng chưa bán hoặc xuất kho.</p>
            <textarea v-model="cancelReason" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-rose-500 focus:ring-rose-500" placeholder="Lý do hủy phiếu nhập" />
        </div>
        <template #footer>
            <div class="flex justify-end gap-2">
                <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700" @click="showCancelModal = false">Quay lại</button>
                <button type="button" :disabled="!cancelReason.trim()" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50" @click="cancelImport">Xác nhận hủy</button>
            </div>
        </template>
    </BaseModal>

    <div
        v-if="loadingDetail"
        class="fixed bottom-4 right-4 rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white shadow-lg"
    >
        Đang tải chi tiết...
    </div>

</div>

</template>
