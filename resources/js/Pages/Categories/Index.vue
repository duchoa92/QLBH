<script setup>
import Swal from 'sweetalert2'
import { router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'

import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({
    layout: AdminLayout
})

const props = defineProps({
    categories: Object,
    filters: Object,
})

const search = ref(props.filters.search || '')

// Xóa
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

            router.delete(
                `/categories/${id}`
            )

        }

    })

}
// Khôi phục
const restore = (id) => {

    router.post(
        `/categories/${id}/restore`
    )

}
// FIX FILTER SEARCH
watch(search, (value) => {
    router.get(
        route('categories.index'),
        { search: value },
        {
            preserveState: true,
            replace: true
        }
    )
})

const showModal = ref(false)

const form = ref({
    name: '',
    parent_id: ''
})

const submit = () => {
    router.post('/categories', form.value, {
        onSuccess: () => {
            showModal.value = false
            form.value = { name: '', parent_id: '' }
        }
    })
}

</script>

<template>

    <div>

        <div class="flex justify-between mb-5">

            <div>

                <h1 class="text-2xl font-bold">
                    Danh mục
                </h1>

                <p class="text-gray-500">
                    Quản lý danh mục sản phẩm
                </p>

            </div>

           <button
                @click="showModal = true"
                class="px-4 py-2 bg-black text-white rounded"
            >
                Thêm mới
            </button>

        </div>

        <div class="mb-5">

            <input
                v-model="search"
                type="text"
                placeholder="Tìm kiếm..."
                class="border rounded p-2 w-64"
            >

        </div>

        <table class="w-full bg-white border">

            <thead>

                <tr class="bg-gray-100">

                    <th class="border p-2">
                        ID
                    </th>

                    <th class="border p-2">
                        Tên
                    </th>

                    <th class="border p-2">
                        Danh mục cha
                    </th>
                    
                    <th class="border p-2">
                        Slug
                    </th>

                    <th class="border p-2">
                        Trạng thái
                    </th>

                    <th class="border p-2">
                        Hành động
                    </th>

                </tr>

            </thead>

            <tbody>

                <tr
                    v-for="item in categories.data"
                    :key="item.id"
                >

                    <td class="border p-2">
                        {{ item.id }}
                    </td>

                    <td class="border p-2">
                        {{ item.name }}
                    </td>

                    <td class="border p-2">
                        {{ item.parent?.name || '-' }}
                    </td>

                    <td class="border p-2">
                        {{ item.slug }}
                    </td>

                    <!-- Trạng thái -->
                    <td class="border p-2">
                        <span
                            v-if="item.deleted_at"
                            class="text-red-600"
                        >
                            Đã xóa
                        </span>

                        <span
                            v-else-if="item.is_active"
                            class="text-green-600"
                        >
                            Hoạt động
                        </span>

                        <span
                            v-else
                            class="text-yellow-600"
                        >
                            Tạm khóa
                        </span>

                    </td>

                    <td class="border p-2">

                        <div class="flex gap-2">

                            <!-- Khôi phục -->
                            <button v-if="item.deleted_at" @click="restore(item.id)" class="px-3 py-1 bg-sky-700 text-white rounded">
                                Khôi phục
                            </button>

                            <a :href="`/categories/${item.id}/edit`" class="px-3 py-1 bg-blue-500 text-white rounded">Sửa</a>
                            <!-- Xóa -->
                            <button @click="destroy(item.id)" class="px-3 py-1 bg-red-500 text-white rounded">
                                Xóa
                            </button>

                        </div>

                    </td>

                </tr>

            </tbody>

        </table>

        <div v-if="showModal" class="fixed inset-0 bg-black/40 flex items-center justify-center">

            <div class="bg-white p-5 rounded-xl w-[400px] space-y-3">

                <h2 class="font-bold">Thêm danh mục</h2>

                <input
                    v-model="form.name"
                    placeholder="Tên danh mục"
                    class="w-full border p-2 rounded"
                />

                <select
                    v-model="form.parent_id"
                    class="w-full border p-2 rounded"
                >
                    <option value="">Không có</option>

                    <option
                        v-for="c in categories.data"
                        :key="c.id"
                        :value="c.id"
                    >
                        {{ c.name }}
                    </option>

                </select>

                <div class="flex justify-end gap-2">
                    <button @click="showModal = false">Hủy</button>
                    <button @click="submit" class="bg-black text-white px-3 py-1 rounded">
                        Lưu
                    </button>
                </div>

            </div>

        </div>

    </div>

</template>