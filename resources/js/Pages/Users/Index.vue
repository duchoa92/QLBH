<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { Plus, ShieldCheck, SquarePen, Trash2 } from 'lucide-vue-next'
import { ref, watch, onBeforeUnmount } from 'vue'
import { useConfirm } from '@/Composables/useConfirm'
import { openModal, registerUrlModal } from '@/Stores/modal'
import PageHeader from '@/Components/UI/PageHeader.vue'
import ActionButton from '@/Components/UI/ActionButton.vue'
import DataPanel from '@/Components/UI/DataPanel.vue'
import ListToolbar from '@/Components/UI/ListToolbar.vue'
import HighlightText from '@/Components/UI/HighlightText.vue'
import { useLiveList } from '@/Composables/useLiveList'
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
const searchValue = ref(props.filters?.search ?? '')
const columns = [
    { key: 'id', label: 'ID', sortable: true, width: '64px' },
    { key: 'name', label: 'Tên', sortable: true },
    { key: 'username', label: 'Tên đăng nhập', sortable: true },
    { key: 'phone', label: 'Điện thoại', sortable: true },
    { key: 'roles', label: 'Vai trò' },
]

// Tìm kiếm/sắp xếp bằng AJAX, không tải lại trang; kết quả được bôi vàng.
const list = useLiveList({ props, dataKey: 'users', params: () => ({ search: searchValue.value.trim() }) })
const { data: usersData, filters: filtersData, loading } = list
watch(searchValue, () => list.schedule())
const sort = ({ field, order }) => list.request({ sort_by: field, sort_order: order })

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
                    <ListToolbar v-model="searchValue" :loading="loading" placeholder="Tìm tên, tên đăng nhập hoặc điện thoại..." @submit="list.request()">
                        <template #actions><div class="flex flex-wrap items-center gap-2 sm:justify-end">
                            <ActionButton v-if="can_manage_roles" variant="secondary" @click="showRoleManager = true">
                                <ShieldCheck class="h-4 w-4" />
                                Vai trò & quyền
                            </ActionButton>
                            <ActionButton v-if="can_create_users" @click="openCreate">
                                <Plus class="h-4 w-4" />
                                Thêm người dùng
                            </ActionButton>
                        </div></template>
                    </ListToolbar>
                </template>

                <BaseTable :data="usersData" :columns="columns" :filters="filtersData" row-clickable @sort="sort" @row-click="openDetail">
                    <template #row="{ row }">
                        <td class="px-3.5 py-2.5 font-medium text-slate-700">{{ row.id }}</td>
                        <td class="px-3.5 py-2.5 font-semibold text-slate-900"><HighlightText :text="row.name" :keyword="searchValue" /></td>
                        <td class="px-3.5 py-2.5 text-slate-600"><HighlightText :text="row.username" :keyword="searchValue" /></td>
                        <td class="px-3.5 py-2.5 text-slate-600"><HighlightText v-if="row.phone" :text="row.phone" :keyword="searchValue" /><template v-else>—</template></td>
                        <td class="px-3.5 py-2.5">
                            <span v-for="role in row.roles" :key="role.id || role" class="mr-1 inline-flex rounded-md bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-800">{{ role.name || role }}</span>
                        </td>
                        <td class="px-3.5 py-2.5 text-center" @click.stop>
                            <div class="app-row-actions">
                                <button type="button" v-if="can_edit_users && row.username !== 'admin'" title="Sửa" @click="openEdit(row)" class="app-row-action app-row-action--edit"><SquarePen class="h-4 w-4" /></button>
                                <button type="button" v-if="can_delete_users && row.username !== 'admin'" title="Xóa" @click="destroy(row)" class="app-row-action app-row-action--danger"><Trash2 class="h-4 w-4" /></button>
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
