<script setup>
import { useForm } from '@inertiajs/vue3'
import BaseModal from '@/Components/UI/BaseModal.vue'
import FloatingInput from '@/Components/UI/FloatingInput.vue'

const props = defineProps({
    category: Object,
    title: String
})

const emit = defineEmits(['close', 'updated'])

const form = useForm({
    id: props.category?.id || null,
    name: props.category?.name || ''
})

const submit = () => {
    if (form.id) {
        form.put(`/categories/${form.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                emit('updated')
                emit('close')
            }
        })
    } else {
        form.post('/categories', {
            preserveScroll: true,
            onSuccess: () => {
                emit('updated')
                emit('close')
            }
        })
    }
}
</script>

<template>
<BaseModal
    :title="props.title || (form.id ? 'Sửa danh mục' : 'Thêm danh mục')"
    @close="emit('close')"
>
    <!-- CONTENT -->
    <div class="space-y-4">

        <FloatingInput
            v-model="form.name"
            label="Tên danh mục"
        />

    </div>

    <!-- FOOTER -->
    <template #footer>
        <div class="flex justify-end gap-2">
            <button @click="emit('close')" class="px-4 py-2 bg-gray-200 rounded">
                Hủy
            </button>

            <button 
                @click="submit"
                class="px-4 py-2 bg-green-600 text-white rounded"
                :disabled="form.processing"
            >
                {{ form.processing ? 'Đang lưu...' : 'Lưu' }}
            </button>
        </div>
    </template>
</BaseModal>
</template>