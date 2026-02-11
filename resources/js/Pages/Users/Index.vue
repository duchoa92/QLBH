<script setup>
import { Link, usePage, router } from '@inertiajs/vue3'
import { computed } from 'vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head } from '@inertiajs/vue3'

const props = defineProps({
    users: Array,
})

/**
 * Lấy permission từ Inertia shared props
 */
const permissions = computed(() => {
    return usePage().props.auth.permissions ?? []
})

/**
 * Hàm check quyền (CHUẨN DUY NHẤT)
 */
const can = (permission) => {
    return permissions.value.includes(permission)
}

//Xóa
const destroyUser = (id) => {
    if (confirm('Xoá người dùng này?')) {
        router.delete(route('users.destroy', id))
    }
}


</script>

<template>
    <Head title="Quản lý người dùng" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Quản lý người dùng
            </h2>
        </template>

        <div class="py-6 max-w-6xl mx-auto">

            <!-- Nút thêm -->
            <div class="mb-4" v-if="can('users.create')">
                <Link
                    :href="route('users.create')"
                    class="px-4 py-2 bg-blue-600 text-white rounded"
                >
                    + Thêm người dùng
                </Link>
            </div>

           <!-- thông báo lỗi -->
            <div v-if="$page.props.flash?.error"
                class="mb-4 p-3 bg-red-100 text-red-700 rounded">
                {{ $page.props.flash.error }}
            </div>

            <div v-if="$page.props.flash?.success"
                class="mb-4 p-3 bg-green-100 text-green-700 rounded">
                {{ $page.props.flash.success }}
            </div>

            <table class="w-full border">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="border px-3 py-2">ID</th>
                        <th class="border px-3 py-2">Tên</th>
                        <th class="border px-3 py-2">Username</th>
                        <th class="border px-3 py-2">Role</th>
                        <th class="border px-3 py-2">Trạng thái</th>
                        <th class="border px-3 py-2">Hành động</th>
                    </tr>
                </thead>

                <tbody>
                    <tr v-for="user in users" :key="user.id">
                        <td class="border px-3 py-2">{{ user.id }}</td>
                        <td class="border px-3 py-2">{{ user.name }}</td>
                        <td class="border px-3 py-2">{{ user.username }}</td>
                        <td class="border px-3 py-2">
                            {{ user.roles.map(r => r.name).join(', ') }}
                        </td>
                        <td class="border px-3 py-2">{{ user.status }}</td>

                        <td class="border px-3 py-2 space-x-2">

                            <!-- Sửa -->
                            <Link
                                v-if="can('users.update')"
                                :href="route('users.edit', user.id)"
                                class="text-blue-600"
                            >
                                Sửa
                            </Link>

                            <!-- Xoá -->
                            <button
                                v-if="can('users.delete')"
                                @click="destroyUser(user.id)"
                                class="text-red-600"
                            >
                                Xoá
                            </button>

                        </td>
                    </tr>
                </tbody>
            </table>

        </div>
    </AuthenticatedLayout>
</template>

