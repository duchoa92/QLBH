<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import { Eye, Plus, Search, SquarePen, Trash2 } from 'lucide-vue-next'
import { ref } from 'vue'
import { useConfirm } from '@/Composables/useConfirm'
import { openModal } from '@/Stores/modal'
import PageHeader from '@/Components/UI/PageHeader.vue'
import ActionButton from '@/Components/UI/ActionButton.vue'
import DataPanel from '@/Components/UI/DataPanel.vue'
import DetailModal from '@/Components/UI/DetailModal.vue'
import UserFormModal from './FormModal.vue'

const props = defineProps({
    users: Object,
    roles: {
        type: Array,
        default: () => [],
    },
})

const confirmBox = useConfirm()
const detailUser = ref(null)
let searchTimeout = null

const search = (event) => {
    clearTimeout(searchTimeout)

    searchTimeout = setTimeout(() => {
        router.get(
            route('users.index'),
            { search: event.target.value },
            {
                preserveState: true,
                replace: true,
            }
        )
    }, 250)
}

const reload = () => {
    router.reload({
        only: ['users'],
        preserveScroll: true,
    })
}

const openCreate = () => {
    openModal(UserFormModal, {
        props: {
            title: 'Thêm người dùng',
            roles: props.roles,
        },
        onUpdated: reload,
    })
}

const openEdit = (user) => {
    openModal(UserFormModal, {
        props: {
            title: 'Sửa người dùng',
            user,
            roles: props.roles,
        },
        onUpdated: reload,
    })
}

const destroy = (user) => {
    confirmBox.show({
        title: 'Xác nhận xóa',
        message: `Xóa tài khoản "${user.name}"? Hành động này không thể hoàn tác.`,
        confirmText: 'Xóa',
        cancelText: 'Hủy',
        onConfirm: () => {
            router.delete(route('users.destroy', user.id), {
                preserveScroll: true,
                onSuccess: reload,
            })
        },
    })
}

const userRows = (user) => [
    { label: 'ID', value: user.id },
    { label: 'Họ tên', value: user.name },
    { label: 'Tên đăng nhập', value: user.username },
    { label: 'Điện thoại', value: user.phone },
    { label: 'Email', value: user.email },
    { label: 'Vai trò', value: (user.roles || []).map(role => role.name || role).join(', ') },
]
</script>

<template>
    <Head title="Quản lý người dùng" />

    <AuthenticatedLayout>
        <div class="space-y-4 p-6">
            <PageHeader
                title="Quản lý người dùng"
                description="Tài khoản, vai trò và thông tin liên hệ"
            >
                <template #actions>
                    <ActionButton @click="openCreate">
                        <Plus class="h-4 w-4" />
                        Thêm người dùng
                    </ActionButton>
                </template>
            </PageHeader>

            <DataPanel>
                <template #header>
                    <div class="relative max-w-md">
                        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input
                            type="text"
                            placeholder="Tìm kiếm..."
                            class="w-full rounded-lg border border-slate-200 py-2 pl-9 pr-3 text-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                            @input="search"
                        >
                    </div>
                </template>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[860px] text-sm">
                        <thead>
                            <tr class="bg-slate-50 text-xs font-semibold uppercase text-slate-500">
                                <th class="px-4 py-3 text-left">ID</th>
                                <th class="px-4 py-3 text-left">Tên</th>
                                <th class="px-4 py-3 text-left">Tên đăng nhập</th>
                                <th class="px-4 py-3 text-left">Điện thoại</th>
                                <th class="px-4 py-3 text-left">Vai trò</th>
                                <th class="w-32 px-4 py-3 text-center">Thao tác</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="user in users.data"
                                :key="user.id"
                                class="hover:bg-slate-50"
                            >
                                <td class="px-4 py-3 font-medium text-slate-700">{{ user.id }}</td>
                                <td class="px-4 py-3 font-semibold text-slate-900">{{ user.name }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ user.username }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ user.phone || '-' }}</td>
                                <td class="px-4 py-3">
                                    <span
                                        v-for="role in user.roles"
                                        :key="role.id || role"
                                        class="mr-1 inline-flex rounded-md bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700"
                                    >
                                        {{ role.name || role }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-center gap-1">
                                        <ActionButton
                                            variant="ghost"
                                            title="Xem chi tiết"
                                            @click="detailUser = user"
                                        >
                                            <Eye class="h-4 w-4" />
                                        </ActionButton>

                                        <ActionButton
                                            variant="ghost"
                                            title="Sửa"
                                            @click="openEdit(user)"
                                        >
                                            <SquarePen class="h-4 w-4 text-blue-600" />
                                        </ActionButton>

                                        <ActionButton
                                            variant="ghost"
                                            title="Xóa"
                                            @click="destroy(user)"
                                        >
                                            <Trash2 class="h-4 w-4 text-red-500" />
                                        </ActionButton>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="!users.data.length">
                                <td
                                    colspan="6"
                                    class="px-4 py-12 text-center text-slate-400"
                                >
                                    Không có dữ liệu
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </DataPanel>

            <DetailModal
                v-if="detailUser"
                title="Chi tiết người dùng"
                :rows="userRows(detailUser)"
                @close="detailUser = null"
            />
        </div>
    </AuthenticatedLayout>
</template>
