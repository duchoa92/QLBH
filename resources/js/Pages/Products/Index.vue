<script setup>
import { computed, ref, watch, onMounted, nextTick } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { canPrintLabels } from '@/utils/printLabels'
import PrintLabelsModal from './PrintLabelsModal.vue'
import HighlightText from '@/Components/UI/HighlightText.vue'
import { useLiveList } from '@/Composables/useLiveList'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Form from './Form.vue'
import ProductImeisModal from './ProductImeisModal.vue'
import ProductDetailModal from './ProductDetailModal.vue'
import { DollarSign, Download, Plus, Printer, SquarePen, Trash2, Upload } from 'lucide-vue-next'
import { useConfirm } from '@/Composables/useConfirm'
import { closeModal, openModal, registerUrlModal } from '@/Stores/modal'
import TrashModal from '@/Components/TrashModal.vue'
import BaseTable from '@/Components/UI/BaseTable.vue'
import PageHeader from '@/Components/UI/PageHeader.vue'
import ActionButton from '@/Components/UI/ActionButton.vue'
import BarcodeCameraButton from '@/Components/BarcodeCameraButton.vue'
import FloatingSelect from '@/Components/UI/FloatingSelect.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import ListToolbar from '@/Components/UI/ListToolbar.vue'
import ListFilterSelect from '@/Components/UI/ListFilterSelect.vue'
import ImportModal from './ImportModal.vue'
import ExportModal from './ExportModal.vue'
import BulkPriceUpdateModal from './BulkPriceUpdateModal.vue'
import { useReferenceData } from '@/Stores/referenceData'
import { imageUrl } from '@/utils/imageUrl'
import { formatCurrency } from '@/utils/format'
 
defineOptions({ layout: AdminLayout })
const props = defineProps({
    products: Object,
    filters: Object,
    categories: Array,
    brands: Array,
    units: {
        type: Array,
        default: () => [],
    },
})
 
const { categories, brands, units } = useReferenceData({
    categories: props.categories,
    brands: props.brands,
    units: props.units,
})
 
const confirmBox = useConfirm()
const page = usePage()
 
const search = ref(props.filters?.search || '')
const category_id = ref(props.filters?.category_id || '')
const brand_id = ref(props.filters?.brand_id || '')
const per_page = ref(props.filters?.per_page || 10) 
 
const isEditingSearch = ref(false)

// Tìm kiếm/lọc/sắp xếp bằng AJAX, không tải lại trang; kết quả được bôi vàng.
const list = useLiveList({
    props,
    dataKey: 'products',
    channels: ['products', 'categories', 'brands', 'units', 'suppliers'],
    params: () => ({
        search: search.value.trim(),
        category_id: category_id.value,
        brand_id: brand_id.value,
        per_page: per_page.value,
    }),
})
const { data: productsData, filters: filtersData, loading } = list
watch(search, () => list.schedule())
// Thương hiệu lọc theo danh mục đã chọn (thương hiệu gắn với danh mục qua category_ids).
// Chưa chọn danh mục thì hiện tất cả thương hiệu.
const brandFilterOptions = computed(() => {
    const categoryId = Number(category_id.value || 0)
    if (!categoryId) return brands.value
    return brands.value.filter(brand => (brand.category_ids || []).map(Number).includes(categoryId))
})
// Đổi danh mục mà thương hiệu đang chọn không thuộc danh mục đó thì bỏ chọn thương hiệu (gộp chung một lần tải).
watch(category_id, () => {
    if (brand_id.value && !brandFilterOptions.value.some(brand => Number(brand.id) === Number(brand_id.value))) {
        brand_id.value = ''
    }
})
watch([category_id, brand_id], () => list.request())
watch(() => filtersData.value?.per_page, (value) => {
    if (value && Number(value) !== Number(per_page.value)) per_page.value = Number(value)
})

const columns = [
    { key: 'id', label: 'ID', sortable: true, width: '60px' },
    { key: 'name', label: 'Tên hàng hóa', sortable: true },
    { key: 'unit', label: 'Đơn vị', width: '76px' },
    { key: 'sell_price', label: 'Giá bán', sortable: true },
    { key: 'stock', label: 'Tồn kho', sortable: true },
    { key: 'is_active', label: 'Trạng thái', sortable: true, width: '120px' }
]
 
// Hàm loadData chuẩn hóa để cập nhật danh sách dựa trên chính bộ lọc hiện tại
const loadData = () => {
    router.reload({ only: ['products'] })
    loadTrashCount()
}
 
 
 
 
const openCreate = () => {
    openModal(Form, {
        props: {
            title: 'Thêm sản phẩm',
            size: 'xl',
            categories: categories.value,
            brands: brands.value,
            units: units.value,
        },
        onUpdated: () => {
            loadData()
            loadTrashCount()
        }
    })
}
 
const openEdit = (item) => {
    openModal(Form, {
        props: {
            title: 'Sửa sản phẩm',
            size: 'xl',
            product: item,
            categories: categories.value,
            brands: brands.value,
            units: units.value,
        },
        onUpdated: () => {
            loadData()
            loadTrashCount()
        }
    })
}
 
const openProductDetail = (item) => {
    openModal(ProductDetailModal, {
        props: {
            productId: item.id,
            productName: item.name,
            onEdit: () => {
                closeModal()
                openEdit(item)
            },
        },
        deepLink: { type: 'product', id: item.id },
    })
}
 
registerUrlModal('product', ProductDetailModal, id => ({ productId: id }))
 
 
/* ================= TRASH ================= */
const trashCount = ref(0)
 
const loadTrashCount = async () => {
    try {
        const res = await fetch('/products/trash', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        const data = await res.json()
        trashCount.value = data.meta?.total || 0
    } catch {
        trashCount.value = 0
    }
}
 
 
const openTrash = () => {
    openModal(TrashModal, {
        props: {
            endpoint: 'products'
        },
        onUpdated: () => {
            loadTrashCount()
            loadData()
        }
    })
}
 
 
const openFromGlobalSearch = () => {
    const url = new URL(window.location.href)
    const productId = Number(url.searchParams.get('open_product'))
    const imeiProductId = Number(url.searchParams.get('open_imei_product'))
    if (!productId && !imeiProductId) return
 
    url.searchParams.delete('open_product')
    url.searchParams.delete('open_imei_product')
    router.replace({
        url: `${url.pathname}${url.search}${url.hash}`,
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => productId ? openProductDetail({ id: productId }) : openImeiList({ id: imeiProductId }),
    })
}
 
watch(() => page.url, () => nextTick(openFromGlobalSearch), { immediate: true })
onMounted(loadTrashCount)
 
/* ================= SELECT ================= */
const selectedIds = ref([])
 
const toggleOne = (id) => {
    selectedIds.value.includes(id)
        ? selectedIds.value = selectedIds.value.filter(i => i !== id)
        : selectedIds.value.push(id)
}
 
const toggleAll = () => {
    const ids = productsData.value?.data?.map(row => row.id) || []
    selectedIds.value = selectedIds.value.length === ids.length ? [] : ids
}
 
const selectedProducts = computed(() =>
    (productsData.value?.data ?? []).filter(product => selectedIds.value.includes(product.id))
)
 
const openPriceUpdate = (products) => {
    if (!products.length) return
 
    openModal(BulkPriceUpdateModal, {
        props: { products },
        onUpdated: () => {
            selectedIds.value = []
            loadData()
        },
    })
}
 
const openBulkPriceUpdate = () => openPriceUpdate(selectedProducts.value)
 
const loadingStatus = ref(null)
 
const toggleStatus = (id) => {
    if (loadingStatus.value) return
 
    loadingStatus.value = id
 
    const item = productsData.value?.data?.find(i => i.id === id)
    if (item) item.is_active = !item.is_active
 
    router.patch(`/products/${id}/toggle-status`, {}, {
        onFinish: () => loadingStatus.value = null
    })
}
 
/* ================= DELETE ================= */
 
 
const destroy = (id) => {
    confirmBox.show({
        title: 'Xác nhận',
        message: 'Chuyển sản phẩm vào thùng rác?',
        confirmText: 'Xóa',
        cancelText: 'Hủy',
        onConfirm: () => {
            router.delete(route('products.destroy', id), {
                preserveScroll: true,
                onSuccess: () => {
                    loadData()
                    loadTrashCount()
                }
            })
        }
    })
}
 
const bulkDelete = () => {
    if (!selectedIds.value.length) return
 
    confirmBox.show({
        title: 'Xác nhận',
        message: `Xóa ${selectedIds.value.length} sản phẩm?`,
        confirmText: 'Xóa',
        cancelText: 'Hủy',
        onConfirm: () => {
            router.post(route('products.bulkDelete'), {
                ids: selectedIds.value
            }, {
                preserveScroll: true,
                onSuccess: () => {
                    selectedIds.value = []
                    loadData() // Load lại danh sách sản phẩm trang hiện tại sau khi xóa nhiều
                    loadTrashCount()
                }
            })
        }
    })
}
 
/* ================= PRINT ================= */
const openPrintLabels = (products) => openModal(PrintLabelsModal, { props: { products } })

const printOne = (product) => openPrintLabels([product])

const canPrintSelected = computed(() => selectedProducts.value.some(canPrintLabels))

const printImei = () => openPrintLabels(selectedProducts.value.filter(canPrintLabels))
 
/* ================= SORT ================= */
 
const sort = ({ field, order }) => list.request({ sort_by: field, sort_order: order })

// ================= PER PAGE =================
const changePerPage = (value) => {
    per_page.value = value
    list.request({ per_page: value })
}

onMounted(() => {
    const saved = localStorage.getItem('per_page')
 
    if (saved && saved != props.filters?.per_page) {
        changePerPage(Number(saved))
    }
})
 
watch(per_page, (val) => {
    localStorage.setItem('per_page', val)
})
 
 
// nhập file / xuất file (hai giao diện riêng)
const openImport = () => {
    openModal(ImportModal, {
        props: {
            endpoint: 'products'
        },
        onUpdated: loadData
    })
}

const openExport = () => {
    openModal(ExportModal)
}
 
const formatMoney = formatCurrency
 
const productUnitName = (row) =>
    row.unit?.short_name || row.unit?.name || 'Cái'
 
const productStockDisplay = (row) => {
    const quantity = row.manage_stock_by_serial || row.imeis?.length
        ? (row.imeis || []).filter(imei => imei.status === 'in_stock').length
        : row.variants?.length
            ? row.variants.reduce((total, variant) => total + Number(variant.stock || 0), 0)
            : Number(row.stock || 0)
 
    return quantity
}
 
const productSellPriceLabel = (row) => {
    // Đồng bộ POS: giá hiển thị là khoảng giá bán của tồn kho hiện có.
    const batchPrices = (row.stock_batches || [])
        .filter(batch => Number(batch.remaining_quantity || 0) > 0)
        .map(batch => Number(batch.sell_price || 0))
        .filter(price => price > 0)
    const imeiPrices = (row.imeis || [])
        .filter(imei => imei.status === 'in_stock')
        .map(imei => Number(imei.sell_price || 0))
        .filter(price => price > 0)
    const variantPrices = (row.variants || [])
        .filter(variant => Number(variant.stock || 0) > 0)
        .map(variant => Number(variant.sell_price || 0))
        .filter(price => price > 0)
    const prices = batchPrices.length ? batchPrices : imeiPrices.length ? imeiPrices : variantPrices
    if (prices.length) {
        const min = Math.min(...prices)
        const max = Math.max(...prices)
        return min === max ? formatMoney(min) : `${formatMoney(min)} - ${formatMoney(max)}`
    }
    return formatMoney(row.sell_price)
}
 
const openImeiList = (row) => {
    openModal(ProductImeisModal, {
        props: { productId: row.id, productName: row.name },
        onUpdated: () => router.reload({ only: ['products'] }),
    })
}
 

</script>
 
<template>
<div class="space-y-3">
 
    <PageHeader title="Sản phẩm" />

   <ListToolbar v-model="search" search-id="product-search" placeholder="Tìm tên, SKU hoặc model..." :loading="loading" @focus="isEditingSearch = true" @blur="isEditingSearch = false">
        <template #filters><ListFilterSelect
            v-model="category_id"
            label="Danh mục"
            :options="categories"
            option-label="name"
            option-value="id"
            placeholder-text="Tất cả danh mục"
        /><ListFilterSelect
            v-model="brand_id"
            label="Thương hiệu"
            :options="brandFilterOptions"
            option-label="name"
            option-value="id"
            placeholder-text="Tất cả thương hiệu"
        /></template>
        <template #search-end><BarcodeCameraButton class="absolute right-8 top-1/2 z-10 -translate-y-1/2" @scanned="(code) => search = code" /></template>
        <template #actions>
            <ActionButton @click="openCreate"><Plus :size="16" />Thêm sản phẩm</ActionButton>
            <ActionButton variant="secondary" @click="openImport"><Upload :size="16" />Nhập file</ActionButton>
            <ActionButton variant="secondary" @click="openExport"><Download :size="16" />Xuất file</ActionButton>
            <ActionButton variant="danger" @click="openTrash"><Trash2 :size="16" />Thùng rác ({{ trashCount }})</ActionButton>
        </template>
    </ListToolbar>
 
    <!-- BULK ACTION -->
    <div
        v-if="selectedIds.length"
        class="mb-3 flex flex-wrap items-center justify-between gap-2 rounded-xl border border-blue-100 bg-blue-50/70 px-3 py-2"
    >
        <span class="text-xs text-slate-600 sm:text-sm">Đã chọn <b class="text-slate-900">{{ selectedIds.length }}</b></span>

        <div class="flex flex-wrap items-center gap-1.5">
            <button
                type="button"
                :disabled="!canPrintSelected"
                :title="canPrintSelected ? 'In IMEI' : 'Các sản phẩm đã chọn không có mã để in'"
                @click="printImei"
                class="inline-flex h-8 items-center gap-1.5 rounded-lg border px-2.5 text-xs font-semibold transition disabled:cursor-not-allowed disabled:opacity-50 border-blue-200 bg-white text-blue-700 hover:bg-blue-50"
            >
                <Printer :size="14" />In IMEI
            </button>
            <button
                type="button"
                @click="openBulkPriceUpdate"
                class="inline-flex h-8 items-center gap-1.5 rounded-lg border px-2.5 text-xs font-semibold transition disabled:cursor-not-allowed disabled:opacity-50 border-emerald-200 bg-white text-emerald-700 hover:bg-emerald-50"
            >
                <DollarSign :size="14" />Sửa giá
            </button>
            <button
                type="button"
                @click="bulkDelete"
                class="inline-flex h-8 items-center gap-1.5 rounded-lg border px-2.5 text-xs font-semibold transition disabled:cursor-not-allowed disabled:opacity-50 border-red-200 bg-white text-red-600 hover:bg-red-50"
            >
                <Trash2 :size="14" />Xóa
            </button>
        </div>
    </div>

    <BaseTable
        :data="productsData"
        :columns="columns"
        :filters="{ ...filtersData, per_page }"
        @sort="sort"
        :selectedIds="selectedIds"
        selectable
        row-clickable
        @toggleOne="toggleOne"
        @toggleAll="toggleAll"
        @row-click="openProductDetail"
    >
        <template #row="{ row }">
 
            <td class="px-3.5 py-2.5 text-center">{{ row.id }}</td>
 
            <td class="px-3.5 py-2.5"><div class="flex items-center gap-2 font-bold text-slate-900">
                <img
                    v-if="imageUrl(row.image_url ?? row.image)"
                    :src="imageUrl(row.image_url ?? row.image)"
                    class="h-8 w-8 rounded object-cover"
                />
                <div
                    v-else
                    class="flex h-8 w-8 items-center justify-center rounded bg-slate-100 text-xs font-bold text-slate-400"
                >
                    SP
                </div>
                <span class="min-w-0"><HighlightText :text="row.name" :keyword="search" /><span v-if="row.sku" class="block text-[11px] font-medium text-slate-400"><HighlightText :text="row.sku" :keyword="search" /></span></span>
            </div></td>
 
            <td class="px-3.5 py-2.5 text-center whitespace-nowrap">
                <div class="font-medium text-slate-700 text-xs">
                    {{ productUnitName(row) }}
                </div>
            </td>
 
            <td class="px-3.5 py-2.5 text-right">
                {{ productSellPriceLabel(row) }}
            </td>
 
            <td class="px-3.5 py-2.5 text-center">
                {{ productStockDisplay(row) }}
            </td>
 
            <!-- STATUS -->
            <td class="px-3.5 py-2.5 text-center">
                <button
                    @click.stop="toggleStatus(row.id)"
                    :disabled="loadingStatus === row.id"
                    class="relative inline-flex h-6 w-11 items-center rounded-full transition"
                    :class="row.is_active ? 'bg-green-500' : 'bg-gray-300'"
                >
                    <span
                        class="inline-block h-4 w-4 transform rounded-full bg-white transition"
                        :class="row.is_active ? 'translate-x-6' : 'translate-x-1'"
                    />
                </button>
            </td>
 
            <!-- ACTION -->
            <td class="px-3.5 py-2.5 text-center" @click.stop>
                <div class="app-row-actions">
                    <Tooltip :text="canPrintLabels(row) ? 'In tem' : 'Không có mã để in tem'" position="top">
                        <button type="button" :disabled="!canPrintLabels(row)" @click="printOne(row)"  class="app-row-action app-row-action--view">
                            <Printer class="h-4 w-4" />
                        </button>
                    </Tooltip>
 
                    <Tooltip text="Sửa">
                        <button type="button" @click="openEdit(row)" title="Sửa" class="app-row-action app-row-action--edit">
                            <SquarePen class="h-4 w-4" />
                        </button>
                    </Tooltip>
 
                    <Tooltip text="Cập nhật giá">
                        <button
                            type="button"
                           
                            @click="openPriceUpdate([row])"
                         class="app-row-action app-row-action--view">
                            <DollarSign class="h-4 w-4" />
                        </button>
                    </Tooltip>
 
                    <Tooltip text="Chuyển vào thùng rác" position="top">
                        <button type="button" @click="destroy(row.id)"  class="app-row-action app-row-action--danger">
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </Tooltip>
                </div>
            </td>
 
        </template>
        <template #mobile-title="{ row }">
            <div class="flex min-w-0 items-center gap-2.5">
                <img v-if="imageUrl(row.image_url ?? row.image)" :src="imageUrl(row.image_url ?? row.image)" class="h-11 w-11 shrink-0 rounded-lg object-cover" />
                <div v-else class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-xs font-bold text-slate-400">SP</div>
                <div class="min-w-0 flex-1">
                    <div class="break-words text-sm font-bold text-slate-900">{{ row.name }}</div>
                    <div v-if="row.sku" class="text-[11px] font-medium text-slate-400">{{ row.sku }}</div>
                </div>
                <button type="button" role="switch" :aria-checked="!!row.is_active" aria-label="Bật/tắt trạng thái" :disabled="loadingStatus === row.id" class="relative inline-flex h-7 w-12 shrink-0 items-center rounded-full transition disabled:opacity-50" :class="row.is_active ? 'bg-green-500' : 'bg-gray-300'" @click.stop="toggleStatus(row.id)">
                    <span class="inline-block h-5 w-5 transform rounded-full bg-white transition" :class="row.is_active ? 'translate-x-6' : 'translate-x-1'" />
                </button>
            </div>
        </template>
        <template #mobile-body="{ row }">
            <div class="grid grid-cols-2 gap-x-3 gap-y-2 text-xs">
                <div><div class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Giá bán</div><div class="mt-0.5 font-bold text-slate-800">{{ productSellPriceLabel(row) }}</div></div>
                <div><div class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Tồn kho</div><div class="mt-0.5 font-bold text-slate-800">{{ productStockDisplay(row) }} <span class="font-medium text-slate-400">{{ productUnitName(row) }}</span></div></div>
            </div>
        </template>
        <template #mobile-actions="{ row }">
            <div class="flex flex-wrap items-center gap-2" @click.stop>
                <button type="button" title="Sửa" aria-label="Sửa" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-700 active:bg-amber-100" @click="openEdit(row)"><SquarePen class="h-5 w-5" /></button>
                <button type="button" title="Giá" aria-label="Giá" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700 active:bg-emerald-100" @click="openPriceUpdate([row])"><DollarSign class="h-5 w-5" /></button>
                <button type="button" :disabled="!canPrintLabels(row)" title="In tem" aria-label="In tem" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-700 active:bg-slate-200 disabled:opacity-40" @click="printOne(row)"><Printer class="h-5 w-5" /></button>
                <button type="button" title="Xóa" aria-label="Xóa" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-50 text-rose-700 active:bg-rose-100" @click="destroy(row.id)"><Trash2 class="h-5 w-5" /></button>
            </div>
        </template>
    </BaseTable>
 
</div>
</template>
 
<style>
@keyframes scaleIn {
  from { transform: scale(0.95); opacity: 0 }
  to { transform: scale(1); opacity: 1 }
}
.animate-scale {
  animation: scaleIn 0.15s ease;
}
</style>