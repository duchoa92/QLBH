<script setup>
import { router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import Swal from 'sweetalert2'

import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({
    layout: AdminLayout
})

const props = defineProps({
    brands: Object,
    filters: Object,
    categories: Array
})

const search = ref(props.filters?.search || '')
const category_id = ref(props.filters?.category_id || '')

// watch giống categories
watch([search, category_id], ([s, c]) => {
    router.get('/brands', {
        search: s,
        category_id: c
    }, {
        preserveState: true,
        replace: true
    })
})

// delete giống categories (Swal)
const destroy = (id) => {
    Swal.fire({
        title: 'Xác nhận xóa?',
        text: 'Dữ liệu sẽ được đưa vào thùng rác',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Xóa',
        cancelButtonText: 'Hủy',
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(`/brands/${id}`)
        }
    })
}

const showModal = ref(false)

const form = ref({
    name: '',
    category_id: ''
})

const submit = () => {
    router.post('/brands', form.value, {
        onSuccess: () => {
            showModal.value = false
            form.value = { name: '', category_id: '' }
        }
    })
}

</script>

<template>
<div>

    <!-- HEADER -->
    <div class="flex justify-between mb-5">
        <div>
            <h1 class="text-2xl font-bold">Thương hiệu</h1>
            <p class="text-gray-500">Quản lý thương hiệu</p>
        </div>

        <button
            @click="showModal = true"
            class="px-4 py-2 bg-black text-white rounded"
        >
            Thêm mới
        </button>
    </div>

    <!-- FILTER -->
    <div class="flex gap-3 mb-5">

        <input
            v-model="search"
            type="text"
            placeholder="Tìm kiếm..."
            class="border rounded p-2 w-64"
        >

        <select v-model="category_id" class="border rounded p-2">
            <option value="">Tất cả danh mục</option>

            <option v-for="c in categories" :key="c.id" :value="c.id">
                {{ c.name }}
            </option>
        </select>

    </div>

    <!-- TABLE -->
    <table class="w-full bg-white border">

        <thead>
            <tr class="bg-gray-100">
                <th class="border p-2">ID</th>
                <th class="border p-2">Tên</th>
                <th class="border p-2">Danh mục</th>
                <th class="border p-2">Trạng thái</th>
                <th class="border p-2">Hành động</th>
            </tr>
        </thead>

        <tbody>

            <tr v-for="brand in brands.data" :key="brand.id">

                <td class="border p-2">{{ brand.id }}</td>

                <td class="border p-2">{{ brand.name }}</td>

                <td class="border p-2">
                    {{ brand.category?.name || '---' }}
                </td>

                <td class="border p-2">
                    <span v-if="brand.is_active" class="text-green-600">
                        Hoạt động
                    </span>
                    <span v-else class="text-yellow-600">
                        Tạm khóa
                    </span>
                </td>

                <td class="border p-2">
                    <div class="flex gap-2">

                        <a :href="`/brands/${brand.id}/edit`"
                           class="px-3 py-1 bg-blue-500 text-white rounded">
                            Sửa
                        </a>

                        <button
                            @click="destroy(brand.id)"
                            class="px-3 py-1 bg-red-500 text-white rounded">
                            Xóa
                        </button>

                    </div>
                </td>

            </tr>

            <tr v-if="brands.data.length === 0">
                <td colspan="5" class="text-center p-5 text-gray-500">
                    Không có dữ liệu
                </td>
            </tr>

        </tbody>

    </table>

    <div v-if="showModal" class="fixed inset-0 bg-black/50 flex items-center justify-center">

    <div class="bg-white w-96 p-5 rounded shadow">

        <h2 class="text-lg font-bold mb-4">
            Thêm thương hiệu
        </h2>

        <!-- Name -->
        <input
            v-model="form.name"
            placeholder="Tên thương hiệu"
            class="border w-full p-2 mb-3 rounded"
        >

        <!-- Category -->
        <select v-model="form.category_id" class="border w-full p-2 mb-3 rounded">
            <option value="">Chọn danh mục</option>
            <option v-for="c in categories" :key="c.id" :value="c.id">
                {{ c.name }}
            </option>
        </select>

        <!-- Action -->
        <div class="flex justify-end gap-2">

            <button
                @click="showModal = false"
                class="px-3 py-1 bg-gray-400 text-white rounded"
            >
                Hủy
            </button>

            <button
                @click="submit"
                class="px-3 py-1 bg-blue-600 text-white rounded"
            >
                Lưu
            </button>

        </div>

    </div>

</div>

</div>
</template>