<script setup>
import { computed, ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { ShieldCheck } from 'lucide-vue-next'
import axios from 'axios'
import { toast } from 'vue-sonner'
import { openModal } from '@/Stores/modal'
import BaseModal from '@/Components/UI/BaseModal.vue'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import FloatingSelect from '@/Components/UI/FloatingSelect.vue'
import ActionButton from '@/Components/UI/ActionButton.vue'
import RoleManagementDialog from './RoleManagementDialog.vue'

const props = defineProps({
    user: {
        type: Object,
        default: null,
    },
    roles: {
        type: Array,
        default: () => [],
    },
    permissions: { type: Array, default: () => [] },
    canManageRoles: { type: Boolean, default: false },
    canAssignSuperAdmin: { type: Boolean, default: false },
    title: {
        type: String,
        default: 'Người dùng',
    },
})

const emit = defineEmits(['close', 'updated'])
const roles = ref([...props.roles])
const permissions = ref([...props.permissions])

const form = useForm({
    name: props.user?.name ?? '',
    username: props.user?.username ?? '',
    phone: props.user?.phone ?? '',
    email: props.user?.email ?? '',
    password: '',
    password_confirmation: '',
    role: props.user?.roles?.[0]?.name ?? props.user?.roles?.[0] ?? '',
})

const roleOptions = computed(() => [
    { name: 'Chọn vai trò', value: '' },
    ...roles.value.map(role => ({
        name: role.name,
        value: role.name,
    })),
])

const openRoleManager = () => {
    const currentRole = roles.value.find(role => role.name === form.role)
    openModal(RoleManagementDialog, {
        props: {
            roles: roles.value,
            permissions: permissions.value,
            initialRoleId: currentRole?.id ?? null,
        },
        onUpdated: refreshRoles,
    })
}

const refreshRoles = async (change = {}) => {
    try {
        const { data } = await axios.get(route('roles.options'))
        permissions.value = data.permissions || []
        const freshRoles = data.roles || []
        roles.value = props.canAssignSuperAdmin ? freshRoles : freshRoles.filter(role => !role.system)

        if (change.deleted && form.role === change.name) form.role = ''
        else if (change.name && roles.value.some(role => role.name === change.name)) form.role = change.name
    } catch {
        toast.error('Không tải được danh sách vai trò mới. Hãy đóng và mở lại biểu mẫu.')
    }

    router.reload({ only: ['users', 'roles', 'permissions'], preserveScroll: true })
}

const submit = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            emit('updated')
            emit('close')
        },
    }

    if (props.user?.id) {
        form.put(route('users.update', props.user.id), options)
        return
    }

    form.post(route('users.store'), options)
}
</script>

<template>
    <BaseModal
        :title="title"
        size="lg"
        @close="emit('close')"
    >
        <div class="grid gap-4 md:grid-cols-2">
            <FloatingInput
                v-model="form.name"
                label="Họ tên"
                :error="form.errors.name"
            />

            <FloatingInput
                v-model="form.username"
                label="Tên đăng nhập"
                :error="form.errors.username"
            />

            <FloatingInput
                v-model="form.phone"
                label="Điện thoại"
                :error="form.errors.phone"
            />

            <FloatingInput
                v-model="form.email"
                type="email"
                label="Email"
                :error="form.errors.email"
            />

            <FloatingInput
                v-model="form.password"
                type="password"
                :label="user ? 'Mật khẩu mới' : 'Mật khẩu'"
                :error="form.errors.password"
            />

            <FloatingInput
                v-model="form.password_confirmation"
                type="password"
                label="Xác nhận mật khẩu"
                :error="form.errors.password_confirmation"
            />

            <div class="md:col-span-2">
                <FloatingSelect
                    v-model="form.role"
                    :options="roleOptions"
                    option-label="name"
                    option-value="value"
                    label="Vai trò"
                    :error="form.errors.role"
                />
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <ActionButton v-if="canManageRoles" variant="secondary" class="!px-2.5 !py-1.5 text-xs" @click="openRoleManager">
                        <ShieldCheck class="h-3.5 w-3.5" /> Quản lý vai trò
                    </ActionButton>
                    <p class="text-xs text-slate-500">Tạo hoặc chỉnh sửa vai trò trong cửa sổ quản lý riêng.</p>
                </div>
            </div>
        </div>

        <template #footer>
            <div class="flex justify-end gap-2">
                <ActionButton
                    variant="secondary"
                    @click="emit('close')"
                >
                    Hủy
                </ActionButton>

                <ActionButton
                    :disabled="form.processing"
                    @click="submit"
                >
                    {{ form.processing ? 'Đang lưu...' : 'Lưu' }}
                </ActionButton>
            </div>
        </template>
    </BaseModal>
</template>
