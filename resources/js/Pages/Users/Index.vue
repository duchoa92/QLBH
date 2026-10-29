<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { Plus, Search, ShieldCheck, SquarePen, Trash2 } from 'lucide-vue-next'
import { ref, watch, onBeforeUnmount } from 'vue'
import { useConfirm } from '@/Composables/useConfirm'
import { openModal, registerUrlModal } from '@/Stores/modal'
import PageHeader from '@/Components/UI/PageHeader.vue'
import ActionButton from '@/Components/UI/ActionButton.vue'
import DataPanel from '@/Components/UI/DataPanel.vue'
import UserFormModal from './FormModal.vue'
import RoleManagementDialog from './RoleManagementDialog.vue'
import BaseTable from '@/Components/UI/BaseTable.vue'
import RecordDetailModal from '@/Components/RecordDetailModal.vue'

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
const page = usePage()

const confirmBox = useConfirm()
const showRoleManager = ref(false)
let searchTimeout = null
const searchValue = ref(props.filters?.search ?? '')
const columns = [
    { key: 'id', label: 'ID', sortable: true, width: '64px' },
    { key: 'name', label: 'Tên', sortable: true },
    { key: 'username', label: 'Tên đăng nhập', sortable: true },
    { key: 'phone', label: 'Điện thoại', sortable: true },
    { key: 'roles', label: 'Vai trò' },
]

const search = (value) => {
    clearTimeout(searchTimeout)

    searchTimeout = setTimeout(() => {
        router.get(
            route('users.index'),
            { search: value || undefined, sort_by: props.filters?.sort_by, sort_order: props.filters?.sort_order, per_page: props.filters?.per_page },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            }
        )
    }, 250)
}

const sort = ({ field, order }) => router.get(route('users.index'), {
    search: searchValue.value || undefined, sort_by: field, sort_order: order, per_page: props.filters?.per_page,
}, { preserveState: true, preserveScroll: true, replace: true })

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

const openDetail = (user) => openModal(RecordDetailModal, {
    props: { type: 'user', recordId: user.id },
    deepLink: { type: 'user', id: user.id },
})
registerUrlModal('user', RecordDetailModal, id => ({ type: 'user', recordId: id }))

const openDetailFromGlobalSearch = () => {
    const url = new URL(window.location.href)
    const userId = Number(url.searchParams.get('open_user'))
    if (!userId) return
    url.searchParams.delete('open_user')
    router.replace({ url: `${url.pathname}${url.search}${url.hash}`, preserveState: true, preserveScroll: true, onSuccess: () => openDetail({ id: userId }) })
}

watch(() => page.url, openDetailFromGlobalSearch, { immediate: true })

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

</script>

<template>
    <Head title="Quản lý người dùng" />

    <AdminLayout>
        <div class="space-y-3">
            <PageHeader
                title="Quản lý người dùng"
            />

            <DataPanel detached>
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

                <BaseTable :data="users" :columns="columns" :filters="filters" row-clickable @sort="sort" @row-click="openDetail">
                    <template #row="{ row }">
                        <td class="border-r p-2 font-medium text-slate-700">{{ row.id }}</td>
                        <td class="border-r p-2 font-semibold text-slate-900">{{ row.name }}</td>
                        <td class="border-r p-2 text-slate-600">{{ row.username }}</td>
                        <td class="border-r p-2 text-slate-600">{{ row.phone || '—' }}</td>
                        <td class="border-r p-2">
                            <span v-for="role in row.roles" :key="role.id || role" class="mr-1 inline-flex rounded-md bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-800">{{ role.name || role }}</span>
                        </td>
                        <td class="p-2 text-center" @click.stop>
                            <div class="flex justify-center gap-1">
                                <ActionButton v-if="can_edit_users && row.username !== 'admin'" variant="ghost" title="Sửa" @click="openEdit(row)"><SquarePen class="h-4 w-4 text-emerald-700" /></ActionButton>
                                <ActionButton v-if="can_delete_users && row.username !== 'admin'" variant="ghost" title="Xóa" @click="destroy(row)"><Trash2 class="h-4 w-4 text-rose-600" /></ActionButton>
                            </div>
                        </td>
                    </template>
                </BaseTable>
            </DataPanel>


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
