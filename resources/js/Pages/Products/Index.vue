<script setup>
import { ref, watch, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Form from './Form.vue'
import ProductImeisModal from './ProductImeisModal.vue'
import ProductDetailModal from './ProductDetailModal.vue'
import { ArrowUpDown, Plus, Printer, QrCode, SquarePen, Trash2 } from 'lucide-vue-next'
import { useConfirm } from '@/Composables/useConfirm'
import { openModal, registerUrlModal } from '@/Stores/modal'
import TrashModal from '@/Components/TrashModal.vue'
import BaseTable from '@/Components/UI/BaseTable.vue'
import PageHeader from '@/Components/UI/PageHeader.vue'
import ActionButton from '@/Components/UI/ActionButton.vue'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import BarcodeCameraButton from '@/Components/BarcodeCameraButton.vue'
import FloatingSelect from '@/Components/UI/FloatingSelect.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import ImportExportModal from './ImportExportModal.vue'
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

let timeout = null
let isSyncingFilters = false

watch([search, category_id, brand_id], () => {
    if (isSyncingFilters || isChangingPerPage.value) {
        isChangingPerPage.value = false
        return
    }

    clearTimeout(timeout)

    timeout = setTimeout(() => {
        router.get('/products', {
            search: search.value,
            category_id: category_id.value,
            brand_id: brand_id.value,
            sort_by: props.filters?.sort_by,
            sort_order: props.filters?.sort_order,
            per_page: per_page.value
        }, {
            preserveState: true,
            preserveScroll: true,
            replace: true
        })
    }, 300)
})

watch(() => props.filters, (filters) => {
    if (!filters) return
    isSyncingFilters = true
    search.value = filters.search || ''
    category_id.value = filters.category_id || ''
    brand_id.value = filters.brand_id || ''
    if (filters.per_page) per_page.value = Number(filters.per_page)
    nextTick(() => { isSyncingFilters = false })
}, { deep: true })



onBeforeUnmount(() => {
    if (timeout) clearTimeout(timeout)
})

const columns = [
    { key: 'id', label: 'ID', sortable: true, width: '60px' },
    { key: 'name', label: 'Tên hàng hóa', sortable: true },
    { key: 'unit', label: 'Đơn vị', width: '76px' },
    { key: 'sell_price', label: 'Giá bán', sortable: true },
    { key: 'stock', label: 'Tồn kho', sortable: true },
    { key: 'is_active', label: 'Trạng thái', sortable: true, width: '120px' }
]

// Khai báo biến timeout phạm vi component
let filterTimeout = null


// Dọn dẹp bộ nhớ: Hủy bỏ hoàn toàn hành động chạy ngầm nếu chuyển trang giữa chừng
onBeforeUnmount(() => {
    if (filterTimeout) {
        clearTimeout(filterTimeout)
    }
})

// Hàm loadData chuẩn hóa để cập nhật danh sách dựa trên chính bộ lọc hiện tại
const loadData = () => {
    router.reload({ only: ['products'] })
    loadTrashCount()
}

/* ================= FORM ================= */
const editingProduct = ref(null)



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
        props: { productId: item.id, productName: item.name },
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
    const ids = props.products?.data?.map(row => row.id) || []
    selectedIds.value = selectedIds.value.length === ids.length ? [] : ids
}

const loadingStatus = ref(null)

const toggleStatus = (id) => {
    if (loadingStatus.value) return

    loadingStatus.value = id

    const item = props.products.data.find(i => i.id === id)
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
const printOne = (id) => {
    window.open(`/products/print?ids=${id}`, '_blank')
}

const printImei = () => {
    if (!selectedIds.value.length) return
    window.open(`/products/print?ids=${selectedIds.value.join(',')}`, '_blank')
}

/* ================= SORT ================= */

const sort = ({ field, order }) => {
    router.get('/products', {
        search: search.value,
        category_id: category_id.value,
        brand_id: brand_id.value,
        per_page: per_page.value,
        sort_by: field,
        sort_order: order
    }, {
        preserveState: true,
        replace: true
    })
}

// ================= PER PAGE =================
const isChangingPerPage = ref(false)

watch(() => props.filters?.per_page, (val) => {
    if (val && val != per_page.value) {
        per_page.value = Number(val)
    }
})

const changePerPage = (value) => {

    isChangingPerPage.value = true

    per_page.value = value
    router.get('/products', {
        search: search.value,
        category_id: category_id.value,
        brand_id: brand_id.value,
        per_page: value,
        sort_by: props.filters?.sort_by,
        sort_order: props.filters?.sort_order,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true
    })
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


// nhập xuất file
const openImportExport = () => {
    openModal(ImportExportModal, {
        props: {
            endpoint: 'products'
        },
        onUpdated: loadData
    })
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

    return `${quantity} ${productUnitName(row)}`
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

    <PageHeader title="Sản phẩm">
        <template #actions>
            <ActionButton @click="openCreate"><Plus :size="16" />Thêm sản phẩm</ActionButton>
            <ActionButton variant="secondary" @click="openImportExport"><ArrowUpDown :size="16" />Nhập / xuất</ActionButton>
            <ActionButton variant="danger" @click="openTrash"><Trash2 :size="16" />Thùng rác ({{ trashCount }})</ActionButton>
        </template>
    </PageHeader>

   <!-- FILTER -->
    <div class="grid grid-cols-1 gap-3 lg:grid-cols-[minmax(260px,1.2fr)_minmax(200px,1fr)_minmax(200px,1fr)]">

        <!-- SEARCH -->
        <div class="relative min-w-0">
            <FloatingInput
                v-model="search"
                label="Tìm tên, SKU hoặc barcode..."
                input-class="pr-12"
            />
            <BarcodeCameraButton class="absolute right-1 top-1/2 z-10 -translate-y-1/2 !h-8 !w-8" @scanned="(code) => search = code" />
        </div>

        <!-- CATEGORY -->
        <FloatingSelect
            v-model="category_id"
            label="Danh mục"
            :options="categories"
            option-label="name"
            option-value="id"
            placeholder-text="Tất cả danh mục"
        />

        <!-- BRAND -->
        <FloatingSelect
            v-model="brand_id"
            label="Thương hiệu"
            :options="brands"
            option-label="name"
            option-value="id"
            placeholder-text="Tất cả thương hiệu"
        />

    </div>

    <!-- BULK ACTION -->
    <div 
        v-if="selectedIds.length"
        class="flex items-center gap-3 bg-blue-50 border rounded p-2 mb-3"
    >

        <span class="text-sm text-gray-600">
            Đã chọn <b>{{ selectedIds.length }}</b> sản phẩm
        </span>

        <button
            @click="printImei"
            class="flex items-center gap-1 text-blue-600 hover:bg-blue-100 px-2 py-1 rounded"
        >
            In IMEI
        </button>

        <button
            @click="bulkDelete"
            class="flex items-center gap-1 text-red-600 hover:bg-red-100 px-2 py-1 rounded"
        >
            Xóa
        </button>

    </div>

    <BaseTable
        :data="products"
        :columns="columns"
        :filters="{ ...filters, per_page }"
        @sort="sort"
        :selectedIds="selectedIds"
        selectable
        row-clickable
        @toggleOne="toggleOne"
        @toggleAll="toggleAll"
        @row-click="openProductDetail"
    >
        <template #row="{ row }">

            <td class="border-r p-2 text-center">{{ row.id }}</td>

            <td class="border-r p-2 flex items-center gap-2">
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
                {{ row.name }}
            </td>

            <td class="border-r px-1 py-2 text-center whitespace-nowrap">
                <div class="font-medium text-slate-700 text-xs">
                    {{ productUnitName(row) }}
                </div>
            </td>

            <td class="border-r p-2 text-right">
                {{ formatMoney(row.sell_price) }}
            </td>

            <td class="border-r p-2 text-center">
                {{ productStockDisplay(row) }}
            </td>

            <!-- STATUS -->
            <td class="border-r p-2 text-center">
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
            <td class="text-center p-1" @click.stop>
                <div class="flex">
                    <Tooltip text="In tem" position="top">
                        <button @click="printOne(row.id)"  class="p-1 hover:bg-gray-200 rounded">
                            <Printer size="17" class="text-gray-500 " />
                        </button>
                    </Tooltip>

                    <Tooltip text="Sửa">
                        <button @click="openEdit(row)" title="Sửa" class="p-1 hover:bg-gray-200 rounded">
                            <SquarePen size="17" class="text-blue-500" />
                        </button>
                    </Tooltip>

                    <Tooltip v-if="row.manage_stock_by_serial" text="Quản lý IMEI">
                        <button
                            type="button"
                            class="p-1 hover:bg-gray-200 rounded"
                            @click="openImeiList(row)"
                        >
                            <QrCode size="17" class="text-indigo-500" />
                        </button>
                    </Tooltip>

                    <Tooltip text="Chuyển vào thùng rác" position="top">
                        <button @click="destroy(row.id)"  class="p-1 hover:bg-gray-200 rounded">
                            <Trash2 size="17" class="text-red-500" />
                        </button>
                    </Tooltip>
                </div>
            </td>

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
