<script setup>
import { ref, watch, onMounted, onBeforeUnmount } from 'vue'
import { router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Form from './Form.vue'
import { ArrowDownUp, ArrowUpDown, FileDown, FileInput, FileOutput, FilePlus, Plus, Printer, SquarePen, Trash2 } from 'lucide-vue-next'
import { useConfirm } from '@/Composables/useConfirm'
import { openModal } from '@/Stores/modal'
import TrashModal from '@/Components/TrashModal.vue'
import BaseTable from '@/Components/UI/BaseTable.vue'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import FloatingSelect from '@/Components/UI/FloatingSelect.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import ImportExportModal from './ImportExportModal.vue'

defineOptions({ layout: AdminLayout })
const props = defineProps({
    products: Object,
    filters: Object,
    categories: Array,
    brands: Array
})

const confirmBox = useConfirm()

const search = ref(props.filters?.search || '')
const category_id = ref(props.filters?.category_id || '')
const brand_id = ref(props.filters?.brand_id || '')

let timeout = null

watch([search, category_id, brand_id], () => {
    clearTimeout(timeout)

    timeout = setTimeout(() => {
        router.get('/products', {
            search: search.value,
            category_id: category_id.value,
            brand_id: brand_id.value,
            sort_by: props.filters?.sort_by,
            sort_order: props.filters?.sort_order
        }, {
            preserveState: true,
            replace: true
        })
    }, 300)
})

onBeforeUnmount(() => {
    if (timeout) clearTimeout(timeout)
})

const columns = [
    { key: 'id', label: 'ID', sortable: true, width: '60px' },
    { key: 'name', label: 'Tên hàng hóa', sortable: true },
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
            categories: props.categories,
            brands: props.brands
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
            categories: props.categories,
            brands: props.brands,
        },
        onUpdated: () => {
            loadData()
            loadTrashCount()
        }
    })
}


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
const handleSort = (sort) => {
    Object.assign(filters.value, sort)
}

const sort = ({ field, order }) => {
    router.get('/products', {
        search: search.value,
        category_id: category_id.value,
        brand_id: brand_id.value,
        sort_by: field,
        sort_order: order
    }, {
        preserveState: true,
        replace: true
    })
}

// nhập xuất file
const openImportExport = () => {
    openModal(ImportExportModal, {
        props: {
            endpoint: 'products'
        },
        onUpdated: loadData
    })
}

</script>

<template>
<div class="space-y-4">

    <!-- HEADER -->
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold">Sản phẩm</h1>
            <p class="text-sm text-gray-500">Quản lý các sản phẩm</p>
        </div>

        <div class="flex gap-2">
            <button @click="openCreate"
                class="flex items-center gap-1 p-2 bg-green-600 text-white rounded hover:bg-green-700">
                <FilePlus /> Thêm
            </button>

            <button 
                @click="openImportExport"
                class="flex items-center gap-1 p-2 bg-green-600 text-white rounded hover:bg-green-700">
                Nhập <ArrowUpDown /> Xuất
            </button>

            <button @click="openTrash"
                class="flex items-center gap-1 border border-red-500 text-red-500 p-2 rounded hover:bg-red-500 hover:text-white">
                <Trash2 /> ({{ trashCount }})
            </button>
        </div>
    </div>

   <!-- FILTER -->
    <div class="flex gap-3 my-5">

        <!-- SEARCH -->
        <FloatingInput
            v-model="search"
            label="Tìm sản phẩm..."
            class="w-64"
        />

        <!-- CATEGORY -->
        <FloatingSelect
            v-model="category_id"
            label="Danh mục"
            :options="[
                { id: '', name: 'Tất cả' },
                ...categories
            ]"
            option-label="name"
            option-value="id"
            class="w-64"
        />

        <!-- BRAND -->
        <FloatingSelect
            v-model="brand_id"
            label="Thương hiệu"
            :options="[
                { id: '', name: 'Tất cả' },
                ...brands.filter(b => !category_id || b.category_id == category_id)
            ]"
            option-label="name"
            option-value="id"
            class="w-64"
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
        :filters="filters"
        @sort="sort"
        :selectedIds="selectedIds"
        selectable
        @toggleOne="toggleOne"
        @toggleAll="toggleAll"
    >
        <template #row="{ row }">

            <td class="border-r p-2 text-center">{{ row.id }}</td>

            <td class="border-r p-2 flex items-center gap-2">
                <img :src="row.image_url" class="w-8 h-8 rounded"/>
                {{ row.name }}
            </td>

            <td class="border-r p-2 text-right">
                {{ row.sell_price }}
            </td>

            <td class="border-r p-2 text-center">
                {{ row.stock }}
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
            <td class="text-center p-1">
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