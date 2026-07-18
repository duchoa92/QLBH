<script setup>
import { router } from '@inertiajs/vue3'
import { ref, watch, onMounted, onBeforeUnmount } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import FloatingSelect from '@/Components/UI/FloatingSelect.vue'
import { openModal } from '@/Stores/modal'
import CategoryForm from './Form.vue'
import TrashModal from '@/Components/TrashModal.vue'
import { useConfirm } from '@/Composables/useConfirm'
import { Plus, Trash2 } from 'lucide-vue-next'

defineOptions({ layout: AdminLayout })

const props = defineProps({
    categories: Object,
    filters: Object
})

const confirmBox = useConfirm()

/* ================= FILTER (CHUẨN) ================= */
const filters = ref({
    search: props.filters?.search || '',
    status: props.filters?.status || '',
})

let timeout = null

watch(filters, (newFilters) => {
    if (timeout) clearTimeout(timeout)

    timeout = setTimeout(() => {
        router.get('/categories', newFilters, {
            preserveState: true,
            replace: true
        })
    }, 300)
}, { deep: true })

onBeforeUnmount(() => {
    if (timeout) clearTimeout(timeout)
})

/* ================= RELOAD ================= */
const loadData = () => {
    router.reload({ only: ['categories'] })
    loadTrashCount()
}

/* ================= MODAL ================= */
const openCreate = () => {
    openModal(CategoryForm, {
        onUpdated: loadData
    })
}

const openEdit = (item) => {
    openModal(CategoryForm, {
        props: { category: item },
        onUpdated: loadData
    })
}

/* ================= DELETE ================= */
const destroy = (id) => {
    confirmBox.show({
        title: 'Xác nhận',
        message: 'Chuyển vào thùng rác?',
        confirmText: 'Xóa',
        cancelText: 'Hủy',
        onConfirm: () => {
            router.delete(`/categories/${id}`, {
                preserveScroll: true,
                onSuccess: loadData
            })
        }
    })
}

/* ================= TRASH ================= */
const trashCount = ref(0)

const loadTrashCount = async () => {
    try {
        const res = await fetch('/categories/trash', {
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
        props: { endpoint: 'categories' },
        onUpdated: loadData
    })
}

onMounted(loadTrashCount)

/* ================= STATUS ================= */
const loadingStatus = ref(null)

const toggleStatus = (id) => {
    if (loadingStatus.value) return

    loadingStatus.value = id

    router.patch(`/categories/${id}/toggle-status`, {}, {
        onFinish: () => loadingStatus.value = null
    })
}
</script>

<template>
<div>

    <!-- HEADER -->
    <div class="flex justify-between mb-5">
        <div>
            <h1 class="text-2xl font-bold">Danh mục</h1>
            <p class="text-gray-500">Quản lý danh mục sản phẩm</p>
        </div>

        <div class="flex gap-2">
            <button @click="openCreate"
                class="flex items-center gap-1 p-2 bg-green-600 text-white rounded hover:bg-green-700 transition">
                <Plus /> Thêm
            </button>

            <button @click="openTrash"
                class="flex items-center gap-1 border border-red-500 text-red-500 p-2 rounded hover:bg-red-500 hover:text-white transition">
                <Trash2 /> ({{ trashCount }})
            </button>
        </div>
    </div>

    <!-- FILTER -->
    <div class="flex gap-3 mb-5">
        <FloatingInput name="search" v-model="filters.search" label="Tìm kiếm..." class="w-64" />

        <FloatingSelect
            v-model="filters.status"
            label="Trạng thái"
            :options="[
                { value: '', label: 'Tất cả' },
                { value: 1, label: 'Hoạt động' },
                { value: 0, label: 'Ngừng' }
            ]"
            class="w-64"
        />
    </div>

    <!-- TABLE -->
    <div class="bg-white rounded-xl shadow border overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-100">
                <tr>
                    <th class="p-2 w-16 text-center">ID</th>
                    <th class="p-2">Tên danh mục</th>
                    <th class="p-2 w-32 text-center">Trạng thái</th>
                    <th class="p-2 w-40 text-center">Hành động</th>
                </tr>
            </thead>

            <tbody>
                <tr v-for="item in categories.data" :key="item.id" class="hover:bg-gray-50">
                    <td class="p-2 text-center">{{ item.id }}</td>
                    <td class="p-2 font-medium">{{ item.name }}</td>

                    <td class="p-2 text-center">
                        <button
                            @click="toggleStatus(item.id)"
                            :disabled="loadingStatus === item.id"
                            class="relative inline-flex h-6 w-11 items-center rounded-full"
                            :class="item.is_active ? 'bg-green-500' : 'bg-gray-300'"
                        >
                            <span
                                class="inline-block h-4 w-4 transform rounded-full bg-white"
                                :class="item.is_active ? 'translate-x-6' : 'translate-x-1'"
                            />
                        </button>
                    </td>

                    <td class="p-2 text-center">
                        <div class="flex gap-2 justify-center">
                            <button @click="openEdit(item)"
                                class="px-2 py-1 bg-blue-500 text-white rounded text-sm">
                                Sửa
                            </button>

                            <button @click="destroy(item.id)"
                                class="px-2 py-1 bg-red-500 text-white rounded text-sm">
                                <Trash2 />
                            </button>
                        </div>
                    </td>
                </tr>

                <tr v-if="categories.data.length === 0">
                    <td colspan="4" class="text-center p-10 text-gray-500">
                        Không có dữ liệu
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

</div>
</template>