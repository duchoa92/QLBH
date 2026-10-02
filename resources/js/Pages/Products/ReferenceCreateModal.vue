<script setup>
import { computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import BaseModal from '@/Components/UI/BaseModal.vue'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import ActionButton from '@/Components/UI/ActionButton.vue'
import api from '@/Services/api'

const props = defineProps({
    type: { type: String, required: true },
    initialName: { type: String, default: '' },
    categoryId: { type: [Number, String], default: null },
    categoryName: { type: String, default: '' },
})
const emit = defineEmits(['close', 'created'])

const labels = { category: 'Danh mục', brand: 'Thương hiệu', unit: 'Đơn vị tính' }
const title = computed(() => `Thêm ${labels[props.type]} mới`)
const form = useForm({ name: props.initialName, short_name: '' })

const submit = async () => {
    form.clearErrors()
    form.processing = true
    const endpoints = { category: '/categories', brand: '/brands', unit: '/units' }
    const payload = { name: form.name.trim(), is_active: true }
    if (props.type === 'brand') payload.category_id = props.categoryId
    if (props.type === 'unit') payload.short_name = form.short_name.trim() || null

    try {
        const { data } = await api.post(endpoints[props.type], payload)
        emit('created', data.data)
        emit('close')
    } catch (error) {
        if (error.response?.status === 422) {
            form.errors = Object.fromEntries(Object.entries(error.response.data.errors || {}).map(([key, messages]) => [key, messages[0]]))
        } else {
            form.errors.name = error.response?.data?.message || 'Không thể lưu dữ liệu.'
        }
    } finally {
        form.processing = false
    }
}
</script>

<template>
    <BaseModal :title="title" size="md" @close="emit('close')">
        <div class="space-y-4">
            <FloatingInput v-model="form.name" :label="`Tên ${labels[type].toLocaleLowerCase()}`" :error="form.errors.name" />
            <template v-if="type === 'brand'">
                <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                    <div class="text-xs font-semibold uppercase text-slate-400">Danh mục liên kết</div>
                    <div class="mt-1 font-semibold text-slate-800">{{ categoryName }}</div>
                </div>
                <p v-if="form.errors.category_id" class="text-sm text-red-500">{{ form.errors.category_id }}</p>
            </template>
            <FloatingInput v-if="type === 'unit'" v-model="form.short_name" label="Tên viết tắt (không bắt buộc)" :error="form.errors.short_name" />
        </div>
        <template #footer>
            <div class="flex justify-end gap-2">
                <ActionButton variant="secondary" @click="emit('close')">Hủy</ActionButton>
                <ActionButton :disabled="form.processing || !form.name.trim()" @click="submit">
                    {{ form.processing ? 'Đang lưu...' : 'Lưu và chọn' }}
                </ActionButton>
            </div>
        </template>
    </BaseModal>
</template>
