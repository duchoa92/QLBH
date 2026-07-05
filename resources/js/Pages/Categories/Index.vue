<script setup>
import { router } from '@inertiajs/vue3'
import { ref, watch, onMounted } from 'vue'
import Swal from 'sweetalert2'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import { openModal } from '@/Stores/modal'
import CategoryForm from './Form.vue'
import Trash from './Trash.vue'

defineOptions({
    layout: AdminLayout
})

const props = defineProps({
    categories: Object,
    filters: Object
})

/* ================= FILTER ================= */
const search = ref(props.filters?.search || '')

let timeout = null

watch(search, (s) => {
    clearTimeout(timeout)
    timeout = setTimeout(() => {
        router.get('/categories', { search: s }, {
            preserveState: true,
            replace: true
        })
    }, 300)
})

/* ================= DELETE ================= */
const destroy = (id) => {
    Swal.fire({
        title: 'Xác nhận chuyển vào thùng rác?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Đồng ý',
        cancelButtonText: 'Hủy'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(`/categories/${id}`, {
                preserveScroll: true,
                onSuccess: () => loadCount()
            })
        }
    })
}

/* ================= MODAL ================= */

const loadData = () => {
    router.reload({ only: ['categories'] })
    loadCount()
}

const openCreate = () => {
    openModal(CategoryForm, {
        title: 'Thêm danh mục',
        onUpdated: loadData
    })
}

const openEdit = (item) => {
    openModal(CategoryForm, {
        title: 'Sửa danh mục',
        props: {
            item
        },
        onUpdated: loadData
    })
}

/* ================= TRASH ================= */


onMounted(() => {
    loadCount()
})

const openTrash = async () => {
    openModal(Trash, {
        title: 'Thùng rác',
        onUpdated: () => loadCount() // 🔥 reload count
    })
}

const trashCount = ref(0)

const loadCount = async () => {
    const res = await fetch('/categories/trash', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    const data = await res.json()
    trashCount.value = data.length
}

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
    <div class="flex justify-between mb-5">
        <div>
            <h1 class="text-2xl font-bold">Danh mục</h1>
            <p class="text-gray-500">Quản lý danh mục sản phẩm</p>
        </div>
        <div class="flex gap-2">
            <button @click="openTrash" class="px-4 py-2 bg-gray-600 text-white rounded">
                Thùng rác ({{ trashCount }})
            </button>
            <button @click="openCreate" class="px-4 py-2 bg-black text-white rounded">
                Thêm mới
            </button>
        </div>
    </div>

    <div class="mb-5">
        <FloatingInput v-model="search" label="Tìm kiếm..." class="w-64" />
    </div>

    <table class="w-full bg-white border">
        <thead>
            <tr class="bg-gray-100">
                <th class="border p-2 w-16">ID</th>
                <th class="border p-2">Tên danh mục</th>
                <th class="border p-2 w-32 text-center">Trạng thái</th>
                <th class="border p-2 w-40 text-center">Hành động</th>
            </tr>
        </thead>
        <tbody>
            <tr v-for="item in categories.data" :key="item.id">
                <td class="border p-2 text-center">{{ item.id }}</td>
                <td class="border p-2">{{ item.name }}</td>

                <td class="border p-2 text-center">
                    <div class="flex items-center justify-center">
                        <button
                            @click="toggleStatus(item.id)"
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
                    <button @click="openEdit(item)" class="px-2 py-1 bg-blue-500 text-white rounded">Sửa</button>
                    <button @click="destroy(item.id)" class="px-2 py-1 bg-red-500 text-white rounded ml-2">Xóa</button>
                </td>
            </tr>
        </tbody>
    </table>
</div>
</template>