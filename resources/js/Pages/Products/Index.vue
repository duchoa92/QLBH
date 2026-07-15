<script setup>
import { ref, watch, onMounted, onBeforeUnmount } from 'vue'
import { router } from '@inertiajs/vue3'
import Form from './Form.vue'
import ProductFilter from './Components/ProductFilter.vue'
import ProductTable from './Components/ProductTable.vue'
import ProductTrashModal from './ProductTrashModal.vue'
import { Plus, Trash2 } from 'lucide-vue-next'
import { useConfirm } from '@/Composables/useConfirm'

const props = defineProps({
    products: Object,
    filters: Object,
    categories: Array,
    brands: Array
})

const showForm = ref(false)
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

const handleEsc = (e) => {
    if (e.key === 'Escape') {
        showForm.value = false
        showTrash.value = false
    }
}

onMounted(() => window.addEventListener('keydown', handleEsc))
onBeforeUnmount(() => window.removeEventListener('keydown', handleEsc))

watch(showForm, (v) => {
    document.body.style.overflow = v ? 'hidden' : ''
})

watch(showTrash, (v) => {
    document.body.style.overflow = v ? 'hidden' : ''
})

const openCreate = () => {
    editingProduct.value = null
    showForm.value = true
}

const openEdit = (product) => {
    editingProduct.value = product
    showForm.value = true
}

/* ================= TRASH ================= */
const trashCount = ref(0)

const loadTrashCount = async () => {
    try {
        const res = await fetch('/products/trash', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        const data = await res.json()
        trashCount.value = data.total
    } catch {
        trashCount.value = 0
    }
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
                    // loadData()
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
                   // loadData() // Load lại danh sách sản phẩm trang hiện tại sau khi xóa nhiều
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

            <button @click="showTrash = true"
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

    <!-- TRASH MODAL -->
    <ProductTrashModal
        :show="showTrash"
        @close="showTrash = false"
        @updated="() => {
            loadTrashCount()
        }"
    />

    <!-- FORM MODAL -->
    <!-- FORM MODAL LEVEL 2 -->
    <div v-if="showForm" class="fixed inset-0 z-[1000]">

        <!-- overlay -->
        <div 
            class="absolute inset-0 bg-black/40 backdrop-blur-sm"
            @click="showForm = false"
        ></div>

        <!-- container -->
        <div class="absolute inset-0 flex items-center justify-center p-4">

            <div 
                class="bg-white w-full max-w-[1100px] h-[90vh] rounded-2xl shadow-xl overflow-hidden flex flex-col animate-scale"
                @click.stop
            >

                <!-- header -->
                <div class="flex justify-between items-center p-4 border-b shrink-0">
                    <h2 class="font-bold text-lg">
                        {{ editingProduct ? 'Sửa sản phẩm' : 'Thêm sản phẩm' }}
                    </h2>

                    <button @click="showForm = false" class="text-red-500 font-bold">
                        ✕
                    </button>
                </div>

                <!-- body scroll -->
                <div class="flex-1 overflow-y-auto p-4 bg-gray-50">

                    <Form
                        :product="editingProduct"
                        :categories="categories"
                        :brands="brands"
                        @close="showForm = false"
                        @updated="() => {
                            showForm = false
                            loadData()
                        }"
                    />

                </div>

            </div>

        </div>
    </div>


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