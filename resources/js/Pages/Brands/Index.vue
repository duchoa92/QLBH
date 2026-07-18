<script setup>
import { router } from '@inertiajs/vue3'
import { ref, watch, onMounted, onBeforeUnmount } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import FloatingSelect from '@/Components/UI/FloatingSelect.vue'
import BrandForm from './Form.vue'
import { Plus, Trash2 } from 'lucide-vue-next'
import TrashModal from '@/Components/TrashModal.vue'
import { useConfirm } from '@/Composables/useConfirm'
import { openModal } from '@/Stores/modal'

defineOptions({ layout: AdminLayout })

const props = defineProps({
    brands: Object,
    filters: Object,
    categories: Array
})

const confirmBox = useConfirm()

/* FILTER */
const search = ref(props.filters?.search || '')
const category_id = ref(props.filters?.category_id ?? '') // Đồng bộ với value trống '' của option Tất cả

let timeout = null
onBeforeUnmount(() => {
    if (timeout) clearTimeout(timeout)
})

// HÀM TRIGGER CHUNG ĐỂ GET DỮ LIỆU
const handleFilter = () => {
    router.get('/brands', { search: search.value, category_id: category_id.value }, {
        preserveState: true,
        replace: true
    })
}

// Chỉ debounce với ô tìm kiếm văn bản (Search)
watch(search, () => {
    clearTimeout(timeout)
    timeout = setTimeout(handleFilter, 300)
})

// Lập tức trigger filter khi người dùng thay đổi select Category
watch(category_id, handleFilter)

/* RELOAD */
const loadData = () => {
    router.reload({ only: ['brands'] })
}

/* DELETE */
const destroy = (id) => {
    confirmBox.show({
        title: 'Xác nhận',
        message: 'Chuyển thương hiệu vào thùng rác?',
        confirmText: 'Xóa',
        cancelText: 'Hủy',
        onConfirm: () => {
            router.delete(`/brands/${id}`, {
                preserveScroll: true,
                onSuccess: () => loadData()
            })
        }
    })
}

const openCreate = () => {
    openModal(BrandForm, {
        props: {
            categories: props.categories
        },
        onUpdated: loadData
    })
}

const openEdit = (brand) => {
    openModal(BrandForm, {
        props: {
            brand,
            categories: props.categories
        },
        onUpdated: loadData
    })
}

/* TRASH COUNT */
const trashCount = ref(0)
const loadTrashCount = async () => {
    try {
        const res = await fetch('/brands/trash', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        if (!res.ok) throw new Error('API lỗi')
        const data = await res.json()
        trashCount.value = data.meta?.total || 0
    } catch (e) {
        console.error('Load trash count lỗi', e)
        trashCount.value = 0
    }
}

onMounted(() => {
    loadTrashCount()
})

const openTrash = () => {
    openModal(TrashModal, {
        props: { endpoint: 'brands' },
        onUpdated: loadData
    })
}

/* STATUS */
const loadingStatus = ref(null)
const toggleStatus = (id) => {
    if (loadingStatus.value) return
    loadingStatus.value = id
    router.patch(`/brands/${id}/toggle-status`, {}, {
        preserveScroll: true,
        onFinish: () => loadingStatus.value = null
    })
}
</script>

<template>
<div>
    <div class="flex justify-between mb-5">
        <div>
            <h1 class="text-2xl font-bold">Thương hiệu</h1>
            <p class="text-gray-500">Quản lý các thương hiệu sản phẩm</p>
        </div>
        <div class="flex gap-2">
            <button @click="openCreate" 
                class="flex items-center gap-1 p-2 bg-green-600 text-white rounded hover:bg-green-700 transition">
                <Plus class="w-4 h-4" /> Thêm mới
            </button>
            <button @click="openTrash"
                class="flex items-center gap-1 border border-red-500 text-red-500 p-2 rounded hover:bg-red-500 hover:text-white transition">
                <Trash2 class="w-4 h-4" />({{ trashCount }})
            </button>
        </div>
    </div>

    <div class="flex gap-3 mb-5">
        <FloatingInput name="search" v-model="search" label="Tìm kiếm..." class="w-64" />
        <FloatingSelect 
            name="category_id"
            v-model="category_id" 
            label="Danh mục"
            :options="[
                { value: '', label: 'Tất cả' },
                ...categories.map(c => ({ value: c.id, label: c.name }))
            ]" class="w-64" />
    </div>

    <div class="bg-white rounded-xl shadow border overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-100 uppercase text-left text-gray-700 border-b">
                    <th class="p-3 w-16 text-center">ID</th>
                    <th class="p-3">Tên thương hiệu</th>
                    <th class="p-3">Thuộc danh mục</th>
                    <th class="p-3 w-32 text-center">Trạng thái</th>
                    <th class="p-3 w-40 text-center">Hành động</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="brand in brands.data" :key="brand.id" class="hover:bg-gray-50 border-b last:border-0">
                    <td class="p-3 text-center text-gray-500">{{ brand.id }}</td>
                    <td class="p-3 font-medium text-gray-900">{{ brand.name }}</td>
                    <td class="p-3">
                        <span class="px-2 py-1 bg-blue-50 text-blue-700 rounded text-xs font-semibold">
                            {{ brand.category?.name || 'Chưa phân loại' }}
                        </span>
                    </td>
                    <td class="p-3 text-center">
                        <div class="flex items-center justify-center">
                            <button
                                @click="toggleStatus(brand.id)"
                                :disabled="loadingStatus === brand.id"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition focus:outline-none"
                                :class="brand.is_active ? 'bg-green-500' : 'bg-gray-300'"
                            >
                                <span
                                    class="inline-block h-4 w-4 transform rounded-full bg-white transition"
                                    :class="brand.is_active ? 'translate-x-6' : 'translate-x-1'"
                                />
                            </button>
                        </div>
                    </td>
                    <td class="p-3 text-center">
                        <div class="flex gap-2 justify-center">
                            <button @click="openEdit(brand)" class="px-3 py-1 bg-blue-500 text-white rounded text-xs hover:bg-blue-600 transition">Sửa</button>
                            <button @click="destroy(brand.id)" class="p-1 bg-red-500 text-white rounded hover:bg-red-600 transition flex items-center justify-center"><Trash2 class="w-3.5 h-3.5" /></button>
                        </div>
                    </td>
                </tr>
                <tr v-if="brands.data.length === 0">
                    <td colspan="5" class="text-center p-10 text-gray-500 bg-gray-50">Không tìm thấy thương hiệu nào phù hợp.</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

</template>