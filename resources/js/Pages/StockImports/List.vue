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
import ListToolbar from '@/Components/UI/ListToolbar.vue'
import ListFilterSelect from '@/Components/UI/ListFilterSelect.vue'
import DateRangeFilter from '@/Components/UI/DateRangeFilter.vue'
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
    ArrowUp,
    ArrowDown,
    ArrowUpDown,
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
const importItemDetail = ref(null)
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

const partyTypeOptions = [
    { value: '', label: 'Tất cả loại phiếu' },
    { value: 'supplier', label: 'Nhà cung cấp' },
    { value: 'customer', label: 'Mua từ khách lẻ' },
]
const statusFilterOptions = [
    { value: '', label: 'Tất cả trạng thái' },
    { value: 'completed', label: 'Hoàn thành' },
    { value: 'draft', label: 'Nháp' },
    { value: 'cancelled', label: 'Đã hủy' },
]
const importColumns = [
    { key: 'code', label: 'Mã Đơn' },
    { key: 'party', label: 'Nhà Cung Cấp / Khách Lẻ', sortable: false },
    { key: 'import_date', label: 'Ngày Nhập', class: 'text-center', align: 'justify-center' },
    { key: 'items_count', label: 'Số Dòng', class: 'text-center', align: 'justify-center' },
    { key: 'grand_total', label: 'Tổng Tiền', class: 'text-right', align: 'justify-end' },
    { key: 'status', label: 'Trạng Thái', class: 'text-center', align: 'justify-center' },
]

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
const detailTendered = (d) => isCustomerImport(d) ? Number(d.grand_total || 0) : Number(d.tendered_amount || d.paid_amount || 0)
const detailChange = (d) => Math.max(0, Number(d.change_amount || 0))

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

const hasItemDetail = (item) => Boolean(item?._variantRows?.length || item?.variant || itemImportImeis(item).length)
const firstAvailablePrice = (...values) => values.find(value => Number(value || 0) > 0) ?? 0

// Giá bán tại thời điểm nhập nằm ở lô tồn kho (lưu theo đơn vị cơ sở). Chỉ khi
// phiếu cũ không có lô mới dùng giá bán hiện tại; cả hai đều quy về đơn vị nhập.
const itemSellPrice = (item) => {
    const factor = Math.max(1, Number(item?.conversion_factor || 1))
    const batchPrice = Number(item?.stock_batch?.sell_price || 0) * factor
    if (batchPrice > 0) return Math.round(batchPrice)
    return Math.round(Number(firstAvailablePrice(item?.variant?.sell_price, item?.product?.sell_price)) * factor)
}
const variantRowOf = (item) => ({
    id: item.id,
    quantity: Number(item.quantity || 0),
    costPrice: Number(firstAvailablePrice(item.cost_price, item.variant?.cost_price)),
    sellPrice: itemSellPrice(item),
    variant: item.variant || null,
    hasVat: Boolean(item.has_vat),
})

const importItemDetailRows = computed(() => {
    const item = importItemDetail.value
    if (!item) return []

    const imeis = itemImportImeis(item)
    if (imeis.length) {
        return imeis.map(history => ({
            key: `imei-${history.id}`,
            type: 'imei',
            imei: history.product_imei?.imei || history.imei || '-',
            serial: history.product_imei?.serial || '',
            variant: history.product_imei?.variant || item.variant,
            // Giá của lịch sử IMEI ưu tiên trước; các phiếu cũ từng lưu 0 thì
            // quay về giá của IMEI/dòng nhập thay vì hiển thị 0 không chính xác.
            costPrice: firstAvailablePrice(history.product_imei?.cost_price, history.cost_price, item.cost_price),
            sellPrice: firstAvailablePrice(history.product_imei?.sell_price, history.sell_price, item.variant?.sell_price, item.product?.sell_price),
            warranty: history.product_imei?.warranty_expired_at || history.meta?.warranty_expired_at,
            hasVat: Boolean(history.meta?.has_vat ?? item.has_vat),
        }))
    }

    const variantRows = item._variantRows?.length ? item._variantRows : (item.variant ? [variantRowOf(item)] : [])
    return variantRows.map(row => ({
        key: `variant-${row.id}`,
        type: 'variant',
        imei: '',
        serial: '',
        variant: row.variant,
        quantity: row.quantity,
        costPrice: row.costPrice,
        sellPrice: row.sellPrice,
        warranty: null,
        hasVat: row.hasVat,
    }))
})

const openImportItemDetail = (item) => { importItemDetail.value = item }

const handleDetailEsc = (event) => {
    if (event.key !== 'Escape') return
    // Không đóng khi đang có modal khác mở phía trên
    if (showCancelModal.value || showCreateModal.value || printPreviewUrl.value || importItemDetail.value) return
    detailImport.value = null
}

watch(detailImport, (value, oldValue) => {
    if (value && !oldValue) window.addEventListener('keydown', handleDetailEsc)
    if (!value && oldValue) {
        importItemDetail.value = null
        window.removeEventListener('keydown', handleDetailEsc)
    }
})

onBeforeUnmount(() => window.removeEventListener('keydown', handleDetailEsc))

const itemImportImeis = (item) =>
    item.import_imeis || []

const priceRange = (values) => {
    const unique = [...new Set(values.map(value => Number(value || 0)))].sort((a, b) => a - b)
    if (unique.length <= 1) return money(unique[0] || 0)
    return `${money(unique[0])} – ${money(unique.at(-1))}`
}

const itemUniqueImeis = (item) => {
    const imeis = itemImportImeis(item)
    return imeis.filter((history, index) =>
        index === imeis.findIndex(candidate => String(candidate.id || candidate.imei) === String(history.id || history.imei))
    )
}

// Với sản phẩm serial, IMEI là nguồn số lượng/giá đáng tin cậy nhất. Những
// phiếu cũ từng có dòng bị nhân bản không được phép làm SL hoặc thành tiền
// tăng theo các dòng đó nữa.
const serialPrices = (item) => itemUniqueImeis(item).map(history =>
    firstAvailablePrice(history.cost_price, history.product_imei?.cost_price, item.cost_price)
)
const displayItemQuantity = (item) => {
    const imeis = itemUniqueImeis(item)
    return imeis.length ? imeis.length : Number(item.quantity || 0)
}
const displayCostPrice = (item) => {
    const serial = serialPrices(item)
    return priceRange(serial.length ? serial : (item._costPrices || [item.cost_price]))
}
const displayItemTotal = (item) => {
    const serial = serialPrices(item)
    if (serial.length) return serial.reduce((sum, price) => sum + Number(price || 0), 0)
    return (item._priceRows || [{ quantity: item.quantity, costPrice: item.cost_price }])
        .reduce((sum, row) => sum + Number(row.quantity || 0) * Number(row.costPrice || 0), 0)
}

// Các phiếu cũ có thể đã lưu mỗi IMEI thành một dòng. Khi xem chi tiết, gộp
// các dòng serial cùng sản phẩm/biến thể/đơn giá để người dùng thấy đúng một
// mặt hàng và số lượng thực tế; IMEI được giữ trong modal con.
const displayImportItems = computed(() => {
    const items = detailImport.value?.items || []
    const grouped = new Map()

    items.forEach(item => {
        const isSerial = Boolean(item.product?.manage_stock_by_serial || itemImportImeis(item).length)

        if (!isSerial) {
            // Sản phẩm thường / có biến thể: các dòng cùng sản phẩm, đơn vị, VAT và
            // bảo hành NCC gộp thành một mặt hàng; mỗi biến thể là một dòng con
            // (giá nhập, giá bán riêng) xem trong modal. Hàng đã qua sử dụng giữ riêng.
            const key = item.extra_info?.is_used
                ? `line-${item.id}`
                : [item.product_id, 'std', item.unit_id || 'none', item.has_vat ? 1 : 0, item.supplier_warranty_value || 0, item.supplier_warranty_unit || ''].join('-')
            const existing = grouped.get(key)
            if (!existing) {
                grouped.set(key, {
                    ...item,
                    _key: key,
                    _variantRows: item.variant ? [variantRowOf(item)] : [],
                    _costPrices: [Number(item.cost_price || 0)],
                    _priceRows: [{ quantity: Number(item.quantity || 0), costPrice: Number(item.cost_price || 0) }],
                })
                return
            }

            existing.quantity = Number(existing.quantity || 0) + Number(item.quantity || 0)
            existing.base_quantity = Number(existing.base_quantity || 0) + Number(item.base_quantity || 0)
            existing._costPrices.push(Number(item.cost_price || 0))
            existing._priceRows.push({ quantity: Number(item.quantity || 0), costPrice: Number(item.cost_price || 0) })
            if (item.variant) existing._variantRows.push(variantRowOf(item))
            return
        }

        // IMEI là đơn vị tồn độc lập: cùng một sản phẩm dù khác biến thể vẫn
        // hiển thị một dòng và để biến thể nằm trong modal danh sách IMEI.
        const key = [item.product_id, 'serial', item.unit_id || 'none'].join('-')
        const existing = grouped.get(key)
        if (!existing) {
            grouped.set(key, {
                ...item,
                _key: key,
                import_imeis: [...itemImportImeis(item)],
                _costPrices: [Number(item.cost_price || 0)],
                _priceRows: [{ quantity: Number(item.quantity || 0), costPrice: Number(item.cost_price || 0) }],
            })
            return
        }

        existing.quantity = Number(existing.quantity || 0) + Number(item.quantity || 0)
        existing.base_quantity = Number(existing.base_quantity || 0) + Number(item.base_quantity || 0)
        existing._costPrices.push(Number(item.cost_price || 0))
        existing._priceRows.push({ quantity: Number(item.quantity || 0), costPrice: Number(item.cost_price || 0) })
        const histories = [...itemImportImeis(existing), ...itemImportImeis(item)]
        existing.import_imeis = histories.filter((history, index, collection) =>
            index === collection.findIndex(candidate => String(candidate.id || candidate.imei) === String(history.id || history.imei))
        )
    })

    return [...grouped.values()]
})


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

            <ListToolbar v-model="search" :loading="loadingImports" placeholder="Tìm mã phiếu, nhà cung cấp / khách, SĐT, sản phẩm, IMEI/Serial..." @submit="submitSearch">
                <template #filters>
                    <ListFilterSelect v-model="partyTypeFilter" label="Loại phiếu" :show-placeholder="false" :options="partyTypeOptions" />
                    <ListFilterSelect v-model="statusFilter" label="Trạng thái" :show-placeholder="false" :options="statusFilterOptions" />
                    <DateRangeFilter v-model:from="dateFrom" v-model:to="dateTo" />
                </template>
                <template #actions>
                    <ActionButton variant="warning" @click="showReplenishmentModal = true"><ClipboardList :size="16" />Lập DS lấy hàng</ActionButton>
                    <ActionButton @click="openCreateModal('supplier')"><Plus :size="16" />Tạo đơn nhập</ActionButton>
                </template>
            </ListToolbar>


            <!-- TABLE -->

            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="hidden overflow-x-auto md:block">

                <table class="app-data-table w-full min-w-[850px] border-collapse text-left text-xs">
                    <thead>
                        <tr class="select-none border-b border-slate-200 bg-slate-50/80 text-[11px] font-extrabold uppercase tracking-wider text-slate-500">
                            <th v-for="col in importColumns" :key="col.key" class="px-3.5 py-2.5 transition" :class="[col.class, col.sortable === false ? '' : 'cursor-pointer hover:bg-slate-100']" @click="col.sortable === false || sortImports(col.key)">
                                <div class="flex items-center gap-1" :class="col.align">
                                    <span>{{ col.label }}</span>
                                    <template v-if="col.sortable !== false">
                                        <template v-if="filtersData?.sort_by === col.key">
                                            <ArrowUp v-if="filtersData?.sort_order === 'asc'" :size="13" class="text-emerald-700" />
                                            <ArrowDown v-else :size="13" class="text-emerald-700" />
                                        </template>
                                        <ArrowUpDown v-else :size="13" class="text-slate-300" />
                                    </template>
                                </div>
                            </th>
                            <th class="w-24 px-2 py-2.5 text-center">Thao Tác</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100 text-xs font-medium">

                        <tr
                            v-for="item in importsData.data"
                            :key="item.id"
                            tabindex="0"
                            class="cursor-pointer transition-colors hover:bg-emerald-50/70"
                            title="Bấm vào dòng để xem chi tiết"
                            @click="showImport(item.id)"
                            @keydown.enter.self="showImport(item.id)"
                        >

                            <!-- CODE -->

                            <td class="px-3.5 py-2.5">

                                <div
                                    class="font-bold text-emerald-700"
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

                            <td class="px-3.5 py-2.5">

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
                                    px-3.5
                                    py-2.5
                                    text-center
                                    text-slate-600
                                "
                            >
                                {{ formatDate(item.import_date) }}
                            </td>


                            <!-- ITEMS -->

                            <td
                                class="
                                    px-3.5
                                    py-2.5
                                    text-center
                                "
                            >
                                {{ item.items_count }}
                            </td>


                            <!-- TOTAL -->

                            <td
                                class="
                                    px-3.5
                                    py-2.5
                                    text-right
                                    font-semibold
                                    text-slate-900
                                "
                            >
                                {{ money(item.grand_total) }}
                            </td>


                            <!-- STATUS -->

                            <td class="px-3.5 py-2.5 text-center">

                                <span
                                    class="inline-flex cursor-pointer items-center gap-1 rounded-full px-2.5 py-0.5 text-[10px] font-bold transition hover:brightness-95"
                                    :class="statusClass(item.status)"
                                    :title="`Lọc: ${statusLabel(item.status)}`"
                                    @click.stop="filterByStatus(item.status || 'completed')"
                                >
                                    <XCircle v-if="item.status === 'cancelled'" class="h-3 w-3" />
                                    <CheckCircle2 v-else class="h-3 w-3" />
                                    {{ statusLabel(item.status) }}
                                </span>

                            </td>

                            <td class="px-3.5 py-2.5 text-center">
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
                                class="px-4 py-12 text-center text-slate-400"
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

            <div class="divide-y divide-slate-100 md:hidden">
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

            <section role="dialog" aria-modal="true" class="relative flex max-h-[92dvh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl bg-slate-50 shadow-2xl ring-1 ring-black/5">

                <!-- HEADER -->
                <div class="flex shrink-0 items-center justify-between border-b border-slate-200 bg-slate-50 px-4 py-3">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                            <FileText class="h-[18px] w-[18px]" />
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-base font-extrabold text-slate-900">Chi tiết đơn nhập</h2>
                            <span class="inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-bold" :class="statusClass(detailImport.status)">
                                <span class="h-1.5 w-1.5 rounded-full bg-current"></span>{{ statusLabel(detailImport.status) }}
                            </span>
                        </div>
                    </div>
                    <button type="button" title="Đóng" class="rounded-lg p-1 text-slate-400 transition hover:bg-slate-200 hover:text-slate-700" @click="detailImport = null">
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <!-- BODY -->
                <div class="min-h-0 flex-1 space-y-3 overflow-y-auto bg-white p-3.5">

                    <!-- 4 thẻ thông tin -->
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        <div class="rounded-xl border border-slate-200/80 bg-slate-50/60 p-2.5">
                            <div class="flex items-center gap-1 text-[10px] font-extrabold uppercase tracking-wider text-slate-400"><Hash class="h-3 w-3" /> Mã đơn nhập</div>
                            <div class="mt-0.5 break-all text-sm font-bold text-slate-900">{{ detailImport.code }}</div>
                            <div class="mt-0.5 text-xs text-slate-500">{{ formatDate(detailImport.import_date) }}</div>
                        </div>

                        <div class="rounded-xl border border-slate-200/80 bg-slate-50/60 p-2.5">
                            <div class="flex items-center gap-1 text-[10px] font-extrabold uppercase tracking-wider text-slate-400"><Truck class="h-3 w-3" /> {{ isCustomerImport(detailImport) ? 'Khách lẻ' : 'Nhà cung cấp' }}</div>
                            <div class="mt-0.5 truncate text-sm font-bold text-slate-900">{{ detailPartyName(detailImport) }}</div>
                            <div class="truncate text-xs font-medium text-slate-400">{{ detailPartyPhone(detailImport) || '-' }}</div>
                        </div>

                        <div class="rounded-xl border border-slate-200/80 bg-slate-50/60 p-2.5">
                            <div class="flex items-center gap-1 text-[10px] font-extrabold uppercase tracking-wider text-slate-400"><UserRound class="h-3 w-3" /> Người tạo</div>
                            <div class="mt-0.5 truncate text-sm font-bold text-slate-900">{{ detailImport.user?.name || '-' }}</div>
                            <div v-if="paymentMethodLabel(detailImport.payment_method) && !isCustomerImport(detailImport)" class="mt-0.5 text-xs text-slate-500">Thanh toán: {{ paymentMethodLabel(detailImport.payment_method) }}</div>
                        </div>

                        <div class="rounded-xl border border-indigo-100 bg-indigo-50/60 p-2.5">
                            <div class="flex items-center gap-1 text-[10px] font-extrabold uppercase tracking-wider text-indigo-500"><Wallet class="h-3 w-3" /> Tổng thanh toán</div>
                            <div class="mt-0.5 text-base font-black text-indigo-700">{{ money(detailImport.grand_total) }}</div>
                            <div class="mt-0.5 text-xs font-medium" :class="detailDebt(detailImport) > 0 ? 'text-orange-600' : 'text-emerald-600'">
                                {{ detailDebt(detailImport) > 0 ? `Còn nợ ${money(detailDebt(detailImport))}` : 'Đã thanh toán đủ' }}
                            </div>
                        </div>
                    </div>

                    <!-- Bảng hàng hóa -->
                    <div class="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-sm">
                        <div class="overflow-x-auto">
                        <table class="w-full min-w-[700px] border-collapse text-left text-xs">
                            <thead>
                                <tr class="border-b border-slate-200 bg-slate-50 text-[11px] font-extrabold uppercase tracking-wider text-slate-500">
                                    <th class="w-10 px-3 py-2 text-center">STT</th>
                                    <th class="px-3 py-2">Sản phẩm</th>
                                    <th class="w-28 px-3 py-2 text-center">Loại</th>
                                    <th class="w-24 px-3 py-2 text-center">SL</th>
                                    <th class="w-28 px-3 py-2 text-right">Đơn giá</th>
                                    <th class="w-32 px-3 py-2 text-right">Thành tiền</th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-slate-100 text-xs font-medium text-slate-700">
                                <template v-for="(item, index) in displayImportItems" :key="item._key || item.id">
                                    <tr class="transition hover:bg-slate-50/60">
                                        <td class="px-3 py-2.5 text-center font-bold text-slate-400">{{ index + 1 }}</td>
                                        <td class="px-3 py-2.5">
                                            <div class="text-sm font-bold text-slate-900">{{ item.product?.name || '-' }}</div>
                                            <div class="text-xs text-slate-400">SKU: {{ item.product?.sku || '-' }}</div>
                                            <button v-if="hasItemDetail(item)" type="button" class="mt-1 inline-flex items-center gap-1 rounded-md border border-indigo-100 bg-indigo-50 px-2 py-0.5 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100" @click="openImportItemDetail(item)">
                                                {{ itemImportImeis(item).length ? `${itemImportImeis(item).length} IMEI / biến thể` : (item._variantRows?.length > 1 ? `${item._variantRows.length} biến thể` : 'Xem biến thể') }} <ChevronRight class="h-3 w-3" />
                                            </button>
                                            <div v-if="item.has_vat || warrantyLabel(item)" class="mt-1 flex flex-wrap items-center gap-2">
                                                <span v-if="item.has_vat" class="inline-flex items-center rounded border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-600">Có VAT</span>
                                                <span v-if="warrantyLabel(item)" class="inline-flex items-center rounded border border-blue-200 bg-blue-50 px-2 py-0.5 text-[11px] font-medium text-blue-600">BH NCC: {{ warrantyLabel(item) }}</span>
                                            </div>
                                            <div v-if="item.extra_info?.is_used" class="mt-1 space-y-0.5 text-[11px] text-amber-800">
                                                <div v-if="item.extra_info.account">Tài khoản: {{ item.extra_info.account }}</div>
                                                <div v-if="item.extra_info.password">Mật khẩu: {{ item.extra_info.password }}</div>
                                            </div>
                                        </td>
                                        <td class="px-3 py-2.5 text-center">
                                            <span class="inline-flex rounded-full border border-blue-100 bg-blue-50 px-2.5 py-0.5 text-xs font-bold text-blue-600">{{ itemKindLabel(item) }}</span>
                                        </td>
                                        <td class="px-3 py-2.5 text-center font-bold text-slate-800">
                                            <div>{{ displayItemQuantity(item) }} {{ item.unit_name || item.unit?.short_name || '' }}</div>
                                            <div v-if="Number(item.conversion_factor || 1) > 1" class="mt-0.5 text-[11px] font-medium text-indigo-600">
                                                = {{ item.base_quantity }} {{ item.product?.unit?.short_name || item.product?.unit?.name || 'đơn vị bán' }}
                                            </div>
                                        </td>
                                        <td class="px-3 py-2.5 text-right whitespace-nowrap text-slate-700">{{ displayCostPrice(item) }}</td>
                                        <td class="px-3 py-2.5 text-right whitespace-nowrap text-sm font-extrabold text-slate-900">{{ money(displayItemTotal(item)) }}</td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                        </div>
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
                            <div class="space-y-1.5 rounded-lg bg-slate-50 p-2.5 text-xs text-slate-600">
                                <div class="flex justify-between gap-4"><span>{{ isCustomerImport(detailImport) ? 'Đã trả khách' : (detailImport.payment_method === 'bank' ? 'Tiền chuyển khoản' : 'Tiền đưa') }}</span><strong class="text-slate-800">{{ money(detailTendered(detailImport)) }}</strong></div>
                                <div class="flex justify-between gap-4"><span>{{ isCustomerImport(detailImport) ? 'Tiền mua lại' : 'Đã trả NCC cho phiếu này' }}</span><strong class="text-emerald-600">{{ money(detailPaid(detailImport)) }}</strong></div>
                                <div v-if="Number(detailImport.old_debt_paid || 0) > 0" class="flex justify-between gap-4"><span>Đã trả nợ cũ</span><strong class="text-amber-600">{{ money(detailImport.old_debt_paid) }}</strong></div>
                                <div v-if="detailChange(detailImport) > 0" class="flex justify-between gap-4"><span>Tiền thừa</span><strong class="text-emerald-600">{{ money(detailChange(detailImport)) }}</strong></div>
                                <div class="flex justify-between gap-4 border-t border-slate-200 pt-1.5"><span :class="detailDebt(detailImport) > 0 ? 'text-orange-600' : 'text-emerald-600'">{{ detailDebt(detailImport) > 0 ? 'Còn thiếu' : 'Trạng thái phiếu' }}</span><strong :class="detailDebt(detailImport) > 0 ? 'text-orange-600' : 'text-emerald-600'">{{ detailDebt(detailImport) > 0 ? money(detailDebt(detailImport)) : 'Đã thanh toán đủ' }}</strong></div>
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

    <BaseModal
        v-if="importItemDetail"
        :title="`IMEI & biến thể · ${importItemDetail.product?.name || 'Sản phẩm'}`"
        size="xl"
        layer-class="z-[1300]"
        body-class="space-y-3 p-3.5"
        @close="importItemDetail = null"
    >
        <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs">
            <div class="min-w-0"><span class="font-bold text-slate-800">{{ importItemDetail.product?.name || '-' }}</span><span class="ml-2 text-slate-500">SKU: {{ importItemDetail.product?.sku || '-' }}</span></div>
            <span class="rounded-full bg-indigo-50 px-2.5 py-1 font-bold text-indigo-700">{{ importItemDetailRows.length }} {{ itemImportImeis(importItemDetail).length ? 'IMEI / Serial' : 'biến thể' }}</span>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[680px] text-left text-xs">
                    <thead class="border-b border-slate-200 bg-slate-50 text-[11px] font-extrabold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-3 py-2">IMEI / Serial</th>
                            <th class="px-3 py-2">Biến thể</th>
                            <th v-if="!itemImportImeis(importItemDetail).length" class="px-3 py-2 text-center">SL</th>
                            <th class="px-3 py-2 text-right">Giá nhập</th>
                            <th class="px-3 py-2 text-right">Giá bán</th>
                            <th class="px-3 py-2">BH NCC</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                        <tr v-for="row in importItemDetailRows" :key="row.key" class="hover:bg-slate-50/60">
                            <td class="px-3 py-2.5">
                                <template v-if="row.type === 'imei'">
                                    <div class="font-mono text-sm font-bold text-slate-900">{{ row.imei }}</div>
                                    <div v-if="row.serial" class="mt-0.5 font-mono text-[11px] text-slate-400">Serial: {{ row.serial }}</div>
                                </template>
                                <span v-else class="text-slate-400">—</span>
                            </td>
                            <td class="px-3 py-2.5"><div class="flex flex-wrap items-center gap-1"><span class="inline-flex rounded-md bg-indigo-50 px-2 py-0.5 font-semibold text-indigo-700">{{ variantLabel(row.variant) }}</span><span v-if="row.hasVat" class="inline-flex rounded-md border border-emerald-200 bg-emerald-50 px-1.5 py-0.5 text-[10px] font-bold text-emerald-700">VAT</span></div></td>
                            <td v-if="!itemImportImeis(importItemDetail).length" class="px-3 py-2.5 text-center font-bold text-slate-800">{{ row.quantity }}</td>
                            <td class="px-3 py-2.5 text-right font-semibold text-slate-800">{{ money(row.costPrice) }}</td>
                            <td class="px-3 py-2.5 text-right font-semibold text-emerald-700">{{ money(row.sellPrice) }}</td>
                            <td class="px-3 py-2.5 text-slate-600">{{ row.warranty ? formatDate(row.warranty) : '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <template #footer>
            <div class="flex justify-end"><button type="button" class="rounded-xl bg-slate-100 px-4 py-2 text-xs font-bold text-slate-700 transition hover:bg-slate-200" @click="importItemDetail = null">Đóng</button></div>
        </template>
    </BaseModal>

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
