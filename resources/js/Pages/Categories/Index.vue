<script setup>
import { router } from '@inertiajs/vue3'
import { ref, watch, onMounted, onBeforeUnmount } from 'vue'
import Swal from 'sweetalert2'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import { openModal } from '@/Stores/modal'
import CategoryForm from './Form.vue'
import TrashModal from '@/Components/TrashModal.vue'

defineOptions({ layout: AdminLayout })

const props = defineProps({
    categories: Object,
    filters: Object
})

/* FILTER */
const search = ref(props.filters?.search || '')

let timeout = null
onBeforeUnmount(() => {
    if (timeout) {
        clearTimeout(timeout)
    }
})

watch(search, (s) => {
    clearTimeout(timeout)
    timeout = setTimeout(() => {
        router.get('/categories', { search: s }, {
            preserveState: true,
            replace: true
        })
    }, 300)
})

const handleTrashUpdated = async () => {
    await loadTrashCount()
}

/* RELOAD */
const loadData = () => {
    router.reload({ only: ['categories'] })
    loadTrashCount()
}

/* DELETE */
const destroy = (id) => {
    Swal.fire({
        title: 'Xác nhận chuyển vào thùng rác?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Đồng ý',
        cancelButtonText: 'Hủy'
    }).then((r) => {
        if (r.isConfirmed) {
            router.delete(`/categories/${id}`, {
                onSuccess: loadData
            })
        }
    })
}

/* MODAL */
const openCreate = () => {
    openModal(CategoryForm, {
        title: 'Thêm danh mục',
        props: {
            brands: props.brands || []
        },
        onUpdated: handleTrashUpdated
    })
}

const openEdit = (item) => {
    openModal(CategoryForm, {
        title: 'Sửa danh mục',
        props: {
            category: item,
            brands: props.brands || []
        },
        onUpdated: handleTrashUpdated
    })
}


/* TRASH COUNT */
onMounted(() => {
    loadTrashCount()
})

const openTrash = () => {
    openModal(TrashModal, {
        title: 'Thùng rác',
        props: {
            endpoint: 'categories'
        },
        onUpdated: () => {
            loadData()
            router.reload({ only: ['categories'] })
        }
    })
}

const trashCount = ref(0)
const loadTrashCount = async () => {
    try {
        const res = await fetch('/categories/trash', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })

        if (!res.ok) throw new Error('API lỗi')

        const data = await res.json()
        trashCount.value = data.length
    } catch (e) {
        console.error('Load trash count lỗi', e)
        trashCount.value = 0
    }
}

/* STATUS */
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
    <div class="flex justify-between mb-5">
        <div>
            <h1 class="text-2xl font-bold">Danh mục</h1>
            <p class="text-gray-500">Quản lý danh mục sản phẩm</p>
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
    </div>

    <table class="w-full bg-white border collapse-separate">
        <thead>
            <tr class="bg-gray-100 text-left">
                <th class="border p-2 w-16 text-center">ID</th>
                <th class="border p-2">Tên danh mục</th>
                <th class="border p-2 w-32 text-center">Trạng thái</th>
                <th class="border p-2 w-40 text-center">Hành động</th>
            </tr>
        </thead>
        <tbody>
            <tr v-for="item in categories.data" :key="item.id" class="hover:bg-gray-50">
                <td class="border p-2 text-center">{{ item.id }}</td>
                <td class="border p-2 font-medium">{{ item.name }}</td>
                <td class="border p-2 text-center">
                    <div class="flex items-center gap-2 justify-center">
                        <button
                            @click="toggleStatus(item.id)"
                            :disabled="loadingStatus === item.id"
                            class="relative inline-flex h-6 w-11 items-center rounded-full transition"
                            :class="item.is_active ? 'bg-green-500' : 'bg-gray-300'"
                        >
                            <span
                                class="inline-block h-4 w-4 transform rounded-full bg-white transition"
                                :class="item.is_active ? 'translate-x-6' : 'translate-x-1'"
                            />
                        </button>
                    </div>
                </td>
                <td class="border p-2 text-center">
                    <div class="flex gap-2 justify-center">
                        <button @click.stop="openEdit(item)" class="px-3 py-1 bg-blue-500 text-white rounded text-sm hover:bg-blue-600">Sửa</button>
                        <button @click.stop="destroy(item.id)" class="px-3 py-1 bg-red-500 text-white rounded text-sm hover:bg-red-600">Xóa</button>
                    </div>
                </td>
            </tr>
            <tr v-if="categories.data.length === 0">
                <td colspan="4" class="text-center p-10 text-gray-500 bg-gray-50">Không tìm thấy danh mục nào phù hợp.</td>
            </tr>
        </tbody>
    </table>
</div>
</template>