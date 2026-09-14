<script setup>
import { useForm } from '@inertiajs/vue3'
import BaseModal from '@/Components/UI/BaseModal.vue'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import FloatingSelect from '@/Components/UI/FloatingSelect.vue'
import ActionButton from '@/Components/UI/ActionButton.vue'

const props = defineProps({
    user: {
        type: Object,
        default: null,
    },
    roles: {
        type: Array,
        default: () => [],
    },
    title: {
        type: String,
        default: 'Người dùng',
    },
})

const emit = defineEmits(['close', 'updated'])

const form = useForm({
    name: props.user?.name ?? '',
    username: props.user?.username ?? '',
    phone: props.user?.phone ?? '',
    email: props.user?.email ?? '',
    password: '',
    password_confirmation: '',
    role: props.user?.roles?.[0]?.name ?? props.user?.roles?.[0] ?? '',
})

const roleOptions = [
    { name: 'Chọn vai trò', value: '' },
    ...props.roles.map(role => ({
        name: role.name,
        value: role.name,
    })),
]

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

            <FloatingSelect
                v-model="form.role"
                class="md:col-span-2"
                :options="roleOptions"
                option-label="name"
                option-value="value"
                label="Vai trò"
                :error="form.errors.role"
            />
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
