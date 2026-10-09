<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import { Eye, Plus, Search, ShieldCheck, SquarePen, Trash2 } from 'lucide-vue-next'
import { ref, watch, onBeforeUnmount } from 'vue'
import { useConfirm } from '@/Composables/useConfirm'
import { openModal } from '@/Stores/modal'
import PageHeader from '@/Components/UI/PageHeader.vue'
import ActionButton from '@/Components/UI/ActionButton.vue'
import DataPanel from '@/Components/UI/DataPanel.vue'
import DetailModal from '@/Components/UI/DetailModal.vue'
import UserFormModal from './FormModal.vue'
import RoleManagementDialog from './RoleManagementDialog.vue'

const props = defineProps({
    users: Object,
    roles: {
        type: Array,
        default: () => [],
    },
    permissions: { type: Array, default: () => [] },
    can_assign_super_admin: { type: Boolean, default: false },
    can_manage_roles: { type: Boolean, default: false },
    can_create_users: { type: Boolean, default: false },
    can_edit_users: { type: Boolean, default: false },
    can_delete_users: { type: Boolean, default: false },
    filters: { type: Object, default: () => ({ search: '' }) },
})

const confirmBox = useConfirm()
const detailUser = ref(null)
const showRoleManager = ref(false)
let searchTimeout = null
const searchValue = ref(props.filters?.search ?? '')

const search = (value) => {
    clearTimeout(searchTimeout)

    searchTimeout = setTimeout(() => {
        router.get(
            route('users.index'),
            { search: value || undefined },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            }
        )
    }, 250)
}

watch(searchValue, search)
onBeforeUnmount(() => clearTimeout(searchTimeout))

const reload = () => {
    router.reload({
        only: ['users', 'roles', 'permissions', 'can_assign_super_admin', 'can_manage_roles', 'can_create_users', 'can_edit_users', 'can_delete_users'],
        preserveScroll: true,
    })
}

const assignableRoles = (user = null) => {
    if (props.can_assign_super_admin) return props.roles
    const existingRoleNames = (user?.roles || []).map(role => role.name || role)
    return props.roles.filter(role => !role.system || existingRoleNames.includes(role.name))
}

const openCreate = () => {
    openModal(UserFormModal, {
        props: {
            title: 'Thêm người dùng',
            roles: assignableRoles(),
            permissions: props.permissions,
            canManageRoles: props.can_manage_roles,
            canAssignSuperAdmin: props.can_assign_super_admin,
        },
        onUpdated: reload,
    })
}

const openEdit = (user) => {
    openModal(UserFormModal, {
        props: {
            title: 'Sửa người dùng',
            user,
            roles: assignableRoles(user),
            permissions: props.permissions,
            canManageRoles: props.can_manage_roles,
            canAssignSuperAdmin: props.can_assign_super_admin,
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

    <AdminLayout>
        <div class="space-y-4 p-6">
            <PageHeader
                title="Quản lý người dùng"
                description="Tài khoản, vai trò và thông tin liên hệ"
            />

            <DataPanel>
                <template #header>
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="relative w-full sm:max-w-md">
                            <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input
                                v-model="searchValue"
                                type="search"
                                placeholder="Tìm tên, tên đăng nhập hoặc điện thoại..."
                                class="w-full rounded-lg border border-slate-200 py-2 pl-9 pr-3 text-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                            >
                        </div>
                        <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                            <ActionButton v-if="can_manage_roles" variant="secondary" @click="showRoleManager = true">
                                <ShieldCheck class="h-4 w-4" />
                                Vai trò & quyền
                            </ActionButton>
                            <ActionButton v-if="can_create_users" @click="openCreate">
                                <Plus class="h-4 w-4" />
                                Thêm người dùng
                            </ActionButton>
                        </div>
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
                                            v-if="can_edit_users && user.username !== 'admin'"
                                            variant="ghost"
                                            title="Xem chi tiết"
                                            @click="detailUser = user"
                                        >
                                            <Eye class="h-4 w-4" />
                                        </ActionButton>

                                        <ActionButton
                                            v-if="can_delete_users && user.username !== 'admin'"
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

            <RoleManagementDialog
                v-if="showRoleManager"
                :roles="roles"
                :permissions="permissions"
                @close="showRoleManager = false"
                @updated="reload"
            />
        </div>
    </AdminLayout>
</template>
