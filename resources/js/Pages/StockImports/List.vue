<script setup>
import { formatCurrency, formatDate as formatConfiguredDate } from '@/utils/format'

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

import {
    ref,
    watch,
    onBeforeUnmount,
} from 'vue'

import {
    router,
} from '@inertiajs/vue3'

import {
    Plus,
    Search,
    Truck,
    ClipboardList,
    Printer,
    SlidersHorizontal,
    RotateCcw,
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


/*
|--------------------------------------------------------------------------
| MODAL
|--------------------------------------------------------------------------
*/

const showCreateModal = ref(false)
const importPartyType = ref('supplier')
const showReplenishmentModal = ref(false)
const detailImport = ref(null)
const printPreviewUrl = ref('')
const loadingDetail = ref(false)


const openCreateModal = (partyType = 'supplier') => {
    importPartyType.value = partyType
    showCreateModal.value = true
}


const closeCreateModal = () => {
    showCreateModal.value = false
}


const handleCreated = () => {

    showCreateModal.value = false

    router.reload({
        only: ['imports'],
    })

}


/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

const search = ref(
    props.filters.search || ''
)
const partyTypeFilter = ref(props.filters.party_type || '')
const statusFilter = ref(props.filters.status || '')
const dateFrom = ref(props.filters.date_from || '')
const dateTo = ref(props.filters.date_to || '')
const showFilters = ref(Boolean(props.filters.party_type || props.filters.status || props.filters.date_from || props.filters.date_to))


let searchTimer = null

const submitSearch = () => {
    clearTimeout(searchTimer)
    router.get('/stock-import', {
        search: search.value.trim(),
        party_type: partyTypeFilter.value,
        status: statusFilter.value,
        date_from: dateFrom.value,
        date_to: dateTo.value,
        per_page: props.filters?.per_page,
        sort_by: props.filters?.sort_by,
        sort_order: props.filters?.sort_order,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

const sortImports = (field) => {
    const sortOrder = props.filters?.sort_by === field && props.filters?.sort_order === 'asc' ? 'desc' : 'asc'
    router.get('/stock-import', {
        search: search.value.trim(), party_type: partyTypeFilter.value, status: statusFilter.value,
        date_from: dateFrom.value, date_to: dateTo.value, per_page: props.filters?.per_page,
        sort_by: field, sort_order: sortOrder,
    }, { preserveState: true, preserveScroll: true, replace: true })
}

watch([search, partyTypeFilter, statusFilter, dateFrom, dateTo], () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(submitSearch, 300)
})

onBeforeUnmount(() => clearTimeout(searchTimer))


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
    imei?.extra_info?.image_path
        ? `/storage/${imei.extra_info.image_path}`
        : null

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
        .finally(() => {
            loadingDetail.value = false
        })

}

</script>


<template>

    <div class="text-slate-800">

    <!-- HEADER -->

    <!-- CONTENT -->

    <div class="space-y-3">
        <PageHeader title="Phiếu nhập hàng" />

        <DataPanel>

            <!-- SEARCH -->

            <div
                class="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between sm:px-5"
            >

                <div class="min-w-0 flex-1 sm:max-w-lg">
                    <div class="relative">
                        <Search :size="18" class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                        <input
                            v-model="search"
                            type="search"
                            placeholder="Tìm mã phiếu, nhà cung cấp / khách, SĐT, CCCD..."
                            @keyup.enter="submitSearch"
                            class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-10 text-sm outline-none transition focus:border-emerald-500 focus:bg-white focus:ring-4 focus:ring-emerald-100"
                        />
                        <button v-if="search" type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-slate-400 hover:text-slate-700" aria-label="Xóa tìm kiếm" @click="search = ''">Xóa</button>
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

            <div class="overflow-x-auto">

                <table class="w-full text-sm">

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

                        </tr>

                    </thead>


                    <tbody>

                        <tr
                            v-for="item in props.imports.data"
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
                                    {{ item.code }}
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
                                    {{ item.party_name || item.customer?.full_name || item.supplier?.name || '—' }}
                                </div>

                                <div v-if="item.party_phone || item.customer?.phone || item.supplier?.phone" class="mt-0.5 text-xs text-slate-500">
                                    {{ item.party_phone || item.customer?.phone || item.supplier?.phone }}
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
                                    class="
                                        inline-flex
                                        rounded-full
                                        bg-emerald-50
                                        px-2.5
                                        py-1
                                        text-xs
                                        font-semibold
                                        text-emerald-600
                                    "
                                >
                                    {{ statusLabel(item.status) }}
                                </span>

                            </td>



                        </tr>


                        <!-- EMPTY -->

                        <tr
                            v-if="!props.imports.data?.length"
                        >

                            <td
                                colspan="6"
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

            <PaginationBar :data="props.imports" :filters="props.filters" />

        </DataPanel>

    </div>


    <!-- ========================================================= -->
    <!-- CREATE MODAL -->
    <!-- ========================================================= -->

    <StockImportFormModal
        v-if="showCreateModal"
        :initial-party-type="importPartyType"
        :categories="props.categories"
        :suppliers="props.suppliers"
        @close="closeCreateModal"
        @created="handleCreated"
    />

    <ReplenishmentListModal
        v-if="showReplenishmentModal"
        :suppliers="props.suppliers"
        @close="showReplenishmentModal = false"
    />

    <BaseModal
        v-if="detailImport"
        title="Chi tiết đơn nhập"
        size="xl"
        @close="detailImport = null"
    >
        <div class="space-y-4">
            <div class="flex justify-end">
                <button type="button" @click="printPreviewUrl = `/stock-import/${detailImport.id}/contract`" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                    <Printer class="h-4 w-4" /> In hợp đồng mua bán
                </button>
            </div>
            <div class="grid gap-3 md:grid-cols-5">
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                    <div class="text-xs font-semibold uppercase text-slate-400">Mã đơn</div>
                    <div class="mt-1 font-semibold text-slate-900">{{ detailImport.code }}</div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 md:col-span-2">
                    <div class="text-xs font-semibold uppercase text-slate-400">Nhà cung cấp / khách lẻ</div>
                    <div class="mt-1 font-semibold text-slate-900">{{ detailImport.party_name || detailImport.customer?.full_name || detailImport.supplier?.name || '-' }}</div>
                    <div class="mt-0.5 text-xs text-slate-500">
                        {{ detailImport.party_phone || detailImport.customer?.phone || detailImport.supplier?.phone || '-' }}
                    </div>
                    <div v-if="detailImport.party_cccd || detailImport.customer?.cccd" class="mt-0.5 text-xs text-slate-500">CCCD: {{ detailImport.party_cccd || detailImport.customer?.cccd }}</div>
                    <div v-if="detailImport.party_address || detailImport.customer?.address" class="mt-0.5 text-xs text-slate-500">{{ detailImport.party_address || detailImport.customer?.address }}</div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                    <div class="text-xs font-semibold uppercase text-slate-400">Ngày nhập</div>
                    <div class="mt-1 font-semibold text-slate-900">{{ formatDate(detailImport.import_date) }}</div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                    <div class="text-xs font-semibold uppercase text-slate-400">Người tạo</div>
                    <div class="mt-1 font-semibold text-slate-900">{{ detailImport.user?.name || '-' }}</div>
                </div>
            </div>

            <div class="grid gap-3 md:grid-cols-4">
                <div class="rounded-xl border border-slate-200 bg-white px-3 py-2">
                    <div class="text-xs font-semibold uppercase text-slate-400">Tiền hàng</div>
                    <div class="mt-1 font-semibold text-slate-900">{{ money(detailImport.total_amount) }}</div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white px-3 py-2">
                    <div class="text-xs font-semibold uppercase text-slate-400">Giảm giá</div>
                    <div class="mt-1 font-semibold text-rose-600">-{{ money(detailImport.discount) }}</div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white px-3 py-2">
                    <div class="text-xs font-semibold uppercase text-slate-400">Phí khác</div>
                    <div class="mt-1 font-semibold text-slate-900">{{ money(detailImport.extra_fee) }}</div>
                </div>

                <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2">
                    <div class="text-xs font-semibold uppercase text-slate-400">Tổng tiền</div>
                    <div class="mt-1 font-semibold text-emerald-600">{{ money(detailImport.grand_total) }}</div>
                </div>
            </div>

            <div class="overflow-x-auto rounded-xl border border-slate-200">
                <table class="w-full min-w-[980px] text-sm">
                    <thead>
                        <tr class="bg-slate-50 text-xs font-semibold uppercase text-slate-500">
                            <th class="px-3 py-2 text-left">Sản phẩm</th>
                            <th class="px-3 py-2 text-left">Biến thể</th>
                            <th class="px-3 py-2 text-center">Đơn vị</th>
                            <th class="px-3 py-2 text-center">Số lượng</th>
                            <th class="px-3 py-2 text-right">Giá nhập</th>
                            <th class="px-3 py-2 text-right">Thành tiền</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                        <template
                            v-for="item in detailImport.items"
                            :key="item.id"
                        >
                            <tr>
                                <td class="px-3 py-3">
                                    <div class="font-semibold text-slate-900">
                                        {{ item.product?.name || '-' }}
                                    </div>
                                    <div class="mt-0.5 text-xs text-slate-500">
                                        SKU: {{ item.product?.sku || '-' }}
                                    </div>
                                    <div v-if="item.extra_info?.is_used" class="mt-1 space-y-0.5 text-[11px] text-amber-800">
                                        <div class="font-semibold">Sản phẩm đã qua sử dụng</div>
                                        <div v-if="item.extra_info.account">Tài khoản: {{ item.extra_info.account }}</div>
                                        <div v-if="item.extra_info.password">Mật khẩu: {{ item.extra_info.password }}</div>
                                    </div>
                                </td>
                                <td class="px-3 py-3 text-xs font-semibold text-indigo-700">
                                    {{ variantLabel(item.variant) }}
                                </td>
                                <td class="px-3 py-3 text-center">{{ item.unit_name || item.unit?.short_name || '-' }}</td>
                                <td class="px-3 py-3 text-center font-semibold">
                                    <div>{{ item.quantity }}</div>
                                    <div
                                        v-if="Number(item.conversion_factor || 1) > 1"
                                        class="mt-0.5 text-[11px] font-medium text-indigo-600"
                                    >
                                        = {{ item.base_quantity }} {{ item.product?.unit?.short_name || item.product?.unit?.name || 'đơn vị bán' }}
                                    </div>
                                </td>
                                <td class="px-3 py-3 text-right">{{ money(item.cost_price) }}</td>
                                <td class="px-3 py-3 text-right font-bold text-slate-900">
                                    {{ money(Number(item.quantity || 0) * Number(item.cost_price || 0)) }}
                                </td>
                            </tr>

                            <tr v-if="itemImportImeis(item).length">
                                <td colspan="6" class="bg-slate-50/70 px-3 py-3">
                                    <div class="mb-2 text-xs font-bold uppercase text-slate-500">
                                        IMEI / Serial đã nhập
                                    </div>

                                    <div class="grid gap-2 md:grid-cols-2">
                                        <div
                                            v-for="history in itemImportImeis(item)"
                                            :key="history.id"
                                            class="rounded-xl border border-slate-200 bg-white p-3 text-xs"
                                        >
                                            <div class="flex flex-wrap items-start justify-between gap-2">
                                                <div>
                                                    <div class="font-mono text-sm font-bold text-slate-900">
                                                        {{ history.product_imei?.imei || history.imei }}
                                                    </div>
                                                    <div
                                                        v-if="history.product_imei?.serial"
                                                        class="mt-0.5 font-mono text-slate-500"
                                                    >
                                                        Serial: {{ history.product_imei.serial }}
                                                    </div>
                                                </div>
                                                <span class="rounded-full bg-emerald-50 px-2 py-0.5 font-bold text-emerald-700">
                                                    {{ history.product_imei?.status || 'in_stock' }}
                                                </span>
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
                                                    <div class="font-semibold text-slate-800">
                                                        {{ formatDate(history.product_imei?.warranty_expired_at || history.meta?.warranty_expired_at) }}
                                                    </div>
                                                </div>
                                            </div>

                                            <div
                                                v-if="hasExtraInfo(history.product_imei)"
                                                class="mt-2 space-y-0.5 rounded-lg bg-slate-50 p-2 text-slate-600"
                                            >
                                                <div v-if="history.product_imei.extra_info?.note">
                                                    {{ history.product_imei.extra_info.note }}
                                                </div>
                                                <div v-if="history.product_imei.extra_info?.account">Tài khoản: {{ history.product_imei.extra_info.account }}</div>
                                                <div v-if="history.product_imei.extra_info?.password">Mật khẩu: {{ history.product_imei.extra_info.password }}</div>
                                                <a
                                                    v-if="extraInfoImageUrl(history.product_imei)"
                                                    :href="extraInfoImageUrl(history.product_imei)"
                                                    target="_blank"
                                                    class="inline-flex font-semibold text-blue-600 hover:text-blue-700"
                                                >
                                                    Xem ảnh thiết bị
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <div
                v-if="detailImport.note"
                class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700"
            >
                {{ detailImport.note }}
            </div>
        </div>
    </BaseModal>
    <PrintPreviewModal :show="Boolean(printPreviewUrl)" title="Xem trước hợp đồng mua bán" :src="printPreviewUrl" @close="printPreviewUrl = ''" />

    <div
        v-if="loadingDetail"
        class="fixed bottom-4 right-4 rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white shadow-lg"
    >
        Đang tải chi tiết...
    </div>

</div>

</template>
