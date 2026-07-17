<script setup>
import { ref, watch, onMounted, onBeforeUnmount } from 'vue'
import { router } from '@inertiajs/vue3'
import Form from './Form.vue'
import ProductFilter from './Components/ProductFilter.vue'
import ProductTable from './Components/ProductTable.vue'
import { Plus, Trash2 } from 'lucide-vue-next'
import { useConfirm } from '@/Composables/useConfirm'
import { openModal } from '@/Stores/modal'
import TrashModal from '@/Components/TrashModal.vue'

const props = defineProps({
    products: Object,
    filters: Object,
    categories: Array,
    brands: Array
})

const confirmBox = useConfirm()

/* ================= FILTER ================= */
const filters = ref({
    search: props.filters?.search || '',
    category_id: props.filters?.category_id || '',
    brand_id: props.filters?.brand_id || '',
    stock: props.filters?.stock || '',
    status: props.filters?.status || '',
    sort_by: props.filters?.sort_by || 'id',
    sort_order: props.filters?.sort_order || 'desc',
    per_page: props.filters?.per_page || 10
})

// Khai báo biến timeout phạm vi component
let filterTimeout = null

// Lắng nghe thay đổi bộ lọc với tính năng Debounce an toàn


watch(filters, (newFilters) => {

    if (filterTimeout) clearTimeout(filterTimeout)

    selectedIds.value = []

    filterTimeout = setTimeout(() => {

        router.get(route('products.index'), {
            ...newFilters,
            page: 1
        }, {
            preserveState: true,
            replace: true,
            preserveScroll: true,
            only: ['products', 'filters', 'brands', 'categories'],
        })

    }, 400)

}, { deep: true })

// Dọn dẹp bộ nhớ: Hủy bỏ hoàn toàn hành động chạy ngầm nếu chuyển trang giữa chừng
onBeforeUnmount(() => {
    if (filterTimeout) {
        clearTimeout(filterTimeout)
    }
})

// Hàm loadData chuẩn hóa để cập nhật danh sách dựa trên chính bộ lọc hiện tại
const loadData = () => {
    router.get(route('products.index'), filters.value, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['products'],
    })
}

/* ================= FORM ================= */
const editingProduct = ref(null)



const openCreate = () => {
    openModal(Form, {
        title: 'Thêm sản phẩm',
        props: {
            categories: props.categories,
            brands: props.brands
        },
        onUpdated: () => {
            loadData()
            loadTrashCount()
        }
    })
}

const openEdit = (product) => {
    openModal(Form, {
        title: 'Sửa sản phẩm',
        props: {
            product,
            categories: props.categories,
            brands: props.brands
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
    const ids = props.products?.data?.map(p => p.id) || []
    selectedIds.value = selectedIds.value.length === ids.length ? [] : ids
}

/* ================= DELETE ================= */
const deleteOne = (id) => {
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
</script>

<template>
<div class="space-y-4">

    <!-- HEADER -->
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold">Hàng hóa</h1>
            <p class="text-sm text-gray-500">Quản lý hàng hóa</p>
        </div>

        <div class="flex gap-2">
            <button @click="openCreate"
                class="flex items-center gap-1 p-2 bg-green-600 text-white rounded hover:bg-green-700">
                <Plus /> Thêm
            </button>

            <button @click="openTrash"
                class="flex items-center gap-1 border border-red-500 text-red-500 p-2 rounded hover:bg-red-500 hover:text-white">
                <Trash2 /> ({{ trashCount }})
            </button>
        </div>
    </div>

    <!-- FILTER -->
    <ProductFilter
        :filters="filters"
        :categories="categories"
        :brands="brands"
        :selectedCount="selectedIds.length"
        @update:filters="v => Object.assign(filters.value, v)"
        @delete="bulkDelete"
        @print="printImei"
    />

    <!-- TABLE -->
    <ProductTable
        :products="products"
        :selectedIds="selectedIds"
        :filters="filters"
        @toggleOne="toggleOne"
        @toggleAll="toggleAll"
        @open="openEdit"
        @sort="handleSort"
        @delete="deleteOne"
        @print="printOne"
    />


  

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