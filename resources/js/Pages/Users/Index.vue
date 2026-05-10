<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

import { Head, Link, router } from '@inertiajs/vue3'

defineProps({
    users: Object,
})

const search = (event) => {

    router.get(
        '/users',
        {
            search: event.target.value,
        },
        {
            preserveState: true,
            replace: true,
        }
    )
}
</script>

<template>

    <Head title="Users" />

    <AuthenticatedLayout>

        <div class="p-6">

            <div class="flex items-center justify-between mb-5">

                <h1 class="text-2xl font-bold">
                    Quản lý User
                </h1>

                <Link
                    :href="route('users.create')"
                    class="rounded bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700"
                >
                    Tạo User
                </Link>
            </div>

            <div class="mb-4">

                <input
                    type="text"
                    placeholder="Tìm kiếm..."
                    class="w-full border rounded px-3 py-2"
                    @input="search"
                >
            </div>

            <div class="overflow-x-auto bg-white rounded shadow">

                <table class="w-full">

                    <thead class="bg-gray-100">

                        <tr>

                            <th class="p-3 text-left">
                                ID
                            </th>

                            <th class="p-3 text-left">
                                Tên
                            </th>

                            <th class="p-3 text-left">
                                Username
                            </th>

                            <th class="p-3 text-left">
                                Phone
                            </th>

                            <th class="p-3 text-left">
                                Role
                            </th>

                            <th class="p-3">
                                Action
                            </th>
                        </tr>
                    </thead>

                    <tbody>

                        <tr
                            v-for="user in users.data"
                            :key="user.id"
                            class="border-t"
                        >

                            <td class="p-3">
                                {{ user.id }}
                            </td>

                            <td class="p-3">
                                {{ user.name }}
                            </td>

                            <td class="p-3">
                                {{ user.username }}
                            </td>

                            <td class="p-3">
                                {{ user.phone }}
                            </td>

                            <td class="p-3">

                                <span
                                    v-for="role in user.roles"
                                    :key="role.id"
                                    class="px-2 py-1 bg-gray-200 rounded text-sm mr-1"
                                >
                                    {{ role.name }}
                                </span>
                            </td>

                            <td class="p-3 text-center">

                                <div class="flex gap-2 justify-center">

                                    <Link
                                        :href="`/users/${user.id}/edit`"
                                        class="px-3 py-1 bg-yellow-500 text-white rounded"
                                    >
                                        Sửa
                                    </Link>

                                    <button
                                        class="px-3 py-1 bg-red-600 text-white rounded"

                                        @click="
                                            router.delete(
                                                `/users/${user.id}`
                                            )
                                        "
                                    >
                                        Xóa
                                    </button>
                                </div>
                            </td>
                        </tr>

                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>