<script setup>
import { useForm } from '@inertiajs/vue3'
import BaseModal from '@/Components/UI/BaseModal.vue'
import FloatingInput from '@/Components/UI/FloatingInput.vue'

// Modal thêm / sửa đơn vị tính (mở từ trang Đơn vị tính, giống Thương hiệu và Danh mục).
const props = defineProps({
    unit: Object,
    title: String,
    modalId: Number,
})

const emit = defineEmits(['close', 'updated'])

const form = useForm({
    name: props.unit?.name || '',
    short_name: props.unit?.short_name || '',
    is_active: props.unit ? Boolean(props.unit.is_active) : true,
})

const submit = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            emit('updated')
            emit('close')
        },
    }
    if (props.unit?.id) form.put(route('units.update', props.unit.id), options)
    else form.post(route('units.store'), options)
}
</script>

<template>
    <BaseModal :title="props.title" size="sm" @close="emit('close')">
        <form class="space-y-4" @submit.prevent="submit">
            <FloatingInput v-model="form.name" name="unit_name" label="Tên đơn vị" placeholder="Ví dụ: Thùng" :error="form.errors.name" />
            <FloatingInput v-model="form.short_name" name="unit_short_name" label="Viết tắt (không bắt buộc)" placeholder="Ví dụ: thg" :error="form.errors.short_name" />
            <p class="rounded-lg bg-blue-50 px-3 py-2 text-xs leading-5 text-blue-700">
                Dùng để hiển thị và quy đổi (ví dụ Thùng → Cái) khi cấu hình sản phẩm và nhập hàng. Tồn kho luôn tính theo đơn vị gốc.
            </p>
            <button type="submit" class="hidden" aria-hidden="true" tabindex="-1" />
        </form>

        <template #footer>
            <div class="flex justify-end gap-2">
                <button type="button" class="rounded bg-gray-200 px-4 py-2" @click="emit('close')">Hủy</button>
                <button type="button" class="rounded bg-green-600 px-4 py-2 text-white" :disabled="form.processing" @click="submit">
                    {{ form.processing ? 'Đang lưu...' : 'Lưu' }}
                </button>
            </div>
        </template>
    </BaseModal>
</template>
