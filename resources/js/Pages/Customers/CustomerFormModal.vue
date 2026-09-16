<script setup>
import { useForm } from '@inertiajs/vue3'
import BaseModal from '@/Components/UI/BaseModal.vue'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import FloatingSelect from '@/Components/UI/FloatingSelect.vue'
import ActionButton from '@/Components/UI/ActionButton.vue'

const props = defineProps({
    customer: {
        type: Object,
        default: null,
    },
    title: {
        type: String,
        default: 'Khách hàng',
    },
})

const emit = defineEmits(['close', 'updated'])

const form = useForm({
    full_name: props.customer?.full_name || '',
    phone: props.customer?.phone || '',
    birthday: props.customer?.birthday || '',
    gender: props.customer?.gender || '',
    cccd: props.customer?.cccd || '',
    province: props.customer?.province || '',
    district: props.customer?.district || '',
    ward: props.customer?.ward || '',
    address: props.customer?.address || '',
    note: props.customer?.note || '',
})

const genderOptions = [
    { label: 'Chưa chọn', value: '' },
    { label: 'Nam', value: 'male' },
    { label: 'Nữ', value: 'female' },
    { label: 'Khác', value: 'other' },
]

const submit = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            emit('updated')
            emit('close')
        },
    }

    if (props.customer?.id) {
        form.put(route('customers.update', props.customer.id), options)
        return
    }

    form.post(route('customers.store'), options)
}
</script>

<template>
    <BaseModal
        :title="title"
        size="xl"
        @close="emit('close')"
    >
        <div class="grid gap-4 md:grid-cols-2">
            <FloatingInput
                v-model="form.full_name"
                label="Họ tên"
                :error="form.errors.full_name"
            />

            <FloatingInput
                v-model="form.phone"
                label="Số điện thoại"
                :error="form.errors.phone"
            />

            <FloatingInput
                v-model="form.birthday"
                type="date"
                label="Ngày sinh"
                :error="form.errors.birthday"
            />

            <FloatingSelect
                v-model="form.gender"
                :options="genderOptions"
                option-label="label"
                option-value="value"
                label="Giới tính"
                :error="form.errors.gender"
            />

            <FloatingInput
                v-model="form.cccd"
                label="CCCD"
                :error="form.errors.cccd"
            />

            <FloatingInput
                v-model="form.province"
                label="Tỉnh / Thành"
                :error="form.errors.province"
            />

            <FloatingInput
                v-model="form.district"
                label="Quận / Huyện"
                :error="form.errors.district"
            />

            <FloatingInput
                v-model="form.ward"
                label="Phường / Xã"
                :error="form.errors.ward"
            />

            <FloatingInput
                v-model="form.address"
                class="md:col-span-2"
                label="Địa chỉ chi tiết"
                :error="form.errors.address"
            />

            <div class="md:col-span-2">
                <label class="mb-1 block text-xs font-semibold uppercase text-slate-400">
                    Ghi chú
                </label>
                <textarea
                    v-model="form.note"
                    rows="3"
                    class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                />
                <p
                    v-if="form.errors.note"
                    class="mt-1 text-sm text-red-500"
                >
                    {{ form.errors.note }}
                </p>
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
