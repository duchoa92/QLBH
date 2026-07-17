<script setup>
import { router } from '@inertiajs/vue3'
import { ref, watch, onMounted, onBeforeUnmount  } from 'vue'
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
const showForm = ref(false)
const editingBrand = ref(null)

/* FILTER */
const search = ref(props.filters?.search || '')
const category_id = ref(props.filters?.category_id ?? null)

let timeout = null
onBeforeUnmount(() => {
    if (timeout) {
        clearTimeout(timeout)
    }
})

watch([search, category_id], ([s, c]) => {
    clearTimeout(timeout)
    timeout = setTimeout(() => {
        router.get('/brands', { search: s, category_id: c }, {
            preserveState: true,
            replace: true
        })
    }, 300)
})


/* RELOAD */
const loadData = () => {
    router.reload({ only: ['brands'] })
    loadTrashCount()
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
                onSuccess: () => {
                    loadData()
                }
            })
        }
    })
}

const openCreate = () => {
    editingBrand.value = null
    showForm.value = true
}

const openEdit = (item) => {
    editingBrand.value = item
    showForm.value = true
}

/* TRASH COUNT */

onMounted(() => {
    loadTrashCount()
})

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

const openTrash = () => {
    openModal(TrashModal, {
        props: {
            endpoint: 'brands'
        },
        onUpdated: loadData
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
            <button @click="openCreate" class="flex items-center gap-1 p-2 bg-green-600 text-white rounded hover:bg-green-700 transition">
                <Plus /> Thêm mới
            </button>
            <button @click="openTrash" class="flex items-center gap-1 border border-red-500 text-red-500 p-2 rounded hover:bg-red-500  hover:text-white transition">
                <Trash2 />({{ trashCount }})
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
        <table class="w-full text-sm border border-gray-200 rounded-lg overflow-hidden">
            <thead>
                <tr class="bg-gray-100 uppercase text-left">
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
                                :disabled="loadingStatus === brand.id"
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
                            <button @click.stop="destroy(brand.id)" class="px-3 py-1 bg-red-500 text-white rounded text-sm hover:bg-red-600"><Trash2 /></button>
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

<div v-if="showForm" class="fixed inset-0 z-50">

    <!-- overlay -->
    <div class="absolute inset-0 bg-black/40"
         @click="showForm = false"></div>

    <!-- modal -->
    <div class="absolute inset-0 flex items-center justify-center">

        <div class="bg-white w-[500px] p-4 rounded shadow">

            <h2 class="font-bold mb-4">
                {{ editingBrand ? 'Sửa thương hiệu' : 'Thêm thương hiệu' }}
            </h2>

            <BrandForm
                :brand="editingBrand"
                :categories="categories"
                @close="showForm = false"
                @updated="() => {
                    showForm = false
                    loadData()
                }"
            />

        </div>

    </div>
</div>
</template>