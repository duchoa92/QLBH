<script setup>
import { router } from '@inertiajs/vue3'
import { ref, watch, onMounted } from 'vue'
import Swal from 'sweetalert2'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import FloatingSelect from '@/Components/UI/FloatingSelect.vue'
import { openModal } from '@/Stores/modal'
import BrandForm from './Form.vue'
import Trash from './Trash.vue'

defineOptions({ layout: AdminLayout })

const props = defineProps({
    brands: Object,
    filters: Object,
    categories: Array
})

/* FILTER */
const search = ref(props.filters?.search || '')
const category_id = ref(props.filters?.category_id ?? null)

let timeout = null

watch([search, category_id], ([s, c]) => {
    clearTimeout(timeout)
    timeout = setTimeout(() => {
        router.get('/brands', { search: s, category_id: c }, {
            preserveState: true,
            replace: true
        })
    }, 300)
})

/* TRASH COUNT */
const trashCount = ref(0)

const getTrashCount = async () => {
    const res = await fetch('/brands/trash')
    const data = await res.json()
    trashCount.value = data.length
}

onMounted(getTrashCount)

/* RELOAD */
const loadData = () => {
    router.reload({ only: ['brands'] })
    getTrashCount()
}

/* DELETE */
const destroy = (id) => {
    Swal.fire({
        title: 'Xác nhận chuyển vào thùng rác?',
        icon: 'warning',
        showCancelButton: true
    }).then((r) => {
        if (r.isConfirmed) {
            router.delete(`/brands/${id}`, {
                onSuccess: loadData
            })
        }
    })
}

/* MODAL */
const openCreate = () => {
    
    console.log('CLICK OK')
    openModal(BrandForm, {
        title: 'Thêm thương hiệu',
        onUpdated: loadData
    })
}

const openEdit = (item) => {
    openModal(BrandForm, {
        title: 'Sửa thương hiệu',
        props: { item },
        onUpdated: loadData
    })
}

const openTrash = () => {
    openModal(Trash, {
        title: 'Thùng rác',
        onUpdated: getTrashCount
    })
}

/* STATUS */
const loadingStatus = ref(null)

const toggleStatus = (id) => {
    if (loadingStatus.value) return

    loadingStatus.value = id

    router.patch(`/brands/${id}/toggle-status`, {}, {
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
            <button @click="openTrash" class="px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700 transition">
                Thùng rác ({{ trashCount }})
            </button>
            <button @click="openCreate" class="px-4 py-2 bg-black text-white rounded hover:bg-gray-800 transition">
                Thêm mới
            </button>
        </div>
    </div>

    <div class="flex gap-3 mb-5">
        <FloatingInput name="search" v-model="search" label="Tìm kiếm..." class="w-64" />
        <FloatingSelect name="category_id" v-model="category_id" label="Danh mục" :options="categories.map(c => ({ value: c.id, label: c.name }))" class="w-64" />
    </div>

    <table class="w-full bg-white border collapse-separate">
        <thead>
            <tr class="bg-gray-100 text-left">
                <th class="border p-2 w-16 text-center">ID</th>
                <th class="border p-2">Tên thương hiệu</th>
                <th class="border p-2">Thuộc danh mục</th>
                <th class="border p-2 w-32 text-center">Trạng thái</th>
                <th class="border p-2 w-40 text-center">Hành động</th>
            </tr>
        </thead>
        <tbody>
            <tr v-for="brand in brands.data" :key="brand.id" class="hover:bg-gray-50">
                <td class="border p-2 text-center">{{ brand.id }}</td>
                <td class="border p-2 font-medium">{{ brand.name }}</td>
                <td class="border p-2">
                    <span class="px-2 py-1 bg-blue-50 text-blue-700 rounded text-sm">
                        {{ brand.category?.name || 'Chưa phân loại' }}
                    </span>
                </td>
                <td class="border p-2 text-center">
                    <div class="flex items-center gap-2 justify-center">
                        <button
                            @click="toggleStatus(brand.id)"
                            class="relative inline-flex h-6 w-11 items-center rounded-full transition"
                            :class="brand.is_active ? 'bg-green-500' : 'bg-gray-300'"
                        >
                            <span
                                class="inline-block h-4 w-4 transform rounded-full bg-white transition"
                                :class="brand.is_active ? 'translate-x-6' : 'translate-x-1'"
                            />
                        </button>
                    </div>
                </td>
                <td class="border p-2 text-center">
                    <div class="flex gap-2 justify-center">
                        <button @click.stop="openEdit(brand)" class="px-3 py-1 bg-blue-500 text-white rounded text-sm hover:bg-blue-600">Sửa</button>
                        <button @click.stop="destroy(brand.id)" class="px-3 py-1 bg-red-500 text-white rounded text-sm hover:bg-red-600">Xóa</button>
                    </div>
                </td>
            </tr>
            <tr v-if="brands.data.length === 0">
                <td colspan="5" class="text-center p-10 text-gray-500 bg-gray-50">Không tìm thấy thương hiệu nào phù hợp.</td>
            </tr>
        </tbody>
    </table>

    <!-- <Modal :show="showModal" :title="isEdit ? 'Cập nhật thương hiệu' : 'Thêm thương hiệu mới'" @close="showModal = false">
        <template #default>
            <FloatingInput 
                name="name" 
                v-model="form.name" 
                label="Thương hiệu" 
                class="mb-3" 
                :disabled="form.processing" 
                :error="form.errors.name" 
            />
            <FloatingSelect 
                name="category_id" 
                v-model="form.category_id" 
                label="Danh mục" 
                :options="categories.map(c => ({ value: c.id, label: c.name }))" 
                :disabled="form.processing" 
                :error="form.errors.category_id" 
                class="mb-3" 
            />
        </template>
        <template #footer>
            <button @click="showModal = false" class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">Hủy</button>
            <button @click="submit" :disabled="form.processing" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 ml-2 shadow-sm">
                <span v-if="form.processing">Đang xử lý...</span>
                <span v-else>{{ isEdit ? 'Cập nhật ngay' : 'Lưu dữ liệu' }}</span>
            </button>
        </template>
    </Modal>

    <Modal :show="showTrash" title="Thùng rác quản lý thương hiệu" maxWidth="2xl" @close="showTrash = false">
        <template #default>
            <div v-if="loadingTrash" class="text-center p-5 text-gray-600 font-medium">Đang đồng bộ dữ liệu...</div>
            <table v-else class="w-full border">
                <thead>
                    <tr class="bg-gray-100 text-left">
                        <th class="p-2 w-16 text-center">ID</th>
                        <th class="p-2">Tên</th>
                        <th class="p-2">Danh mục</th>
                        <th class="p-2 w-48 text-center">Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="b in trashData" :key="b.id" class="border-b hover:bg-gray-50">
                        <td class="p-2 text-center">{{ b.id }}</td>
                        <td class="p-2 font-medium">{{ b.name }}</td>
                        <td class="p-2">{{ b.category?.name || '---' }}</td>
                        <td class="p-2 text-center">
                            <div class="flex gap-2 justify-center">
                                <button @click.stop="restore(b.id)" class="px-2 py-1 bg-green-600 text-white text-xs rounded hover:bg-green-700">Khôi phục</button>
                                <button @click.stop="forceDelete(b.id)" class="px-2 py-1 bg-red-600 text-white text-xs rounded hover:bg-red-700">Xóa vĩnh viễn</button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="trashData.length === 0">
                        <td colspan="4" class="text-center p-6 text-gray-400 bg-gray-50">Thùng rác hiện tại trống.</td>
                    </tr>
                </tbody>
            </table>
        </template>
    </Modal> -->
</div>
</template>