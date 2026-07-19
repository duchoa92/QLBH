<script setup>
import { useForm } from '@inertiajs/vue3'
import BaseModal from '@/Components/UI/BaseModal.vue'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import FloatingSelect from '@/Components/UI/FloatingSelect.vue'

const props = defineProps({
    brand: Object,
    categories: Array,
    title: String,
    modalId: Number
})

const emit = defineEmits(['close', 'updated'])

const form = useForm({
    id: props.brand?.id || null,
    name: props.brand?.name || '',
    category_id: props.brand?.category_id ?? ''
})

const submit = () => {
    if (form.id) {
        form.put(`/brands/${form.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                emit('updated')
                emit('close')
            }
        })
    } else {
        form.post('/brands', {
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
    :title= "props.title"
    @close="emit('close')"
>
    <!-- content -->
    <div class="space-y-4">
        <FloatingInput
            v-model="form.name"
            label="Tên thương hiệu"
            class="mb-4"
        />

        <FloatingSelect 
            name="category_id"
            v-model="form.category_id" 
            label="Danh mục"
            :options="[
                { value: '', label: '-- chọn danh mục --' },
                ...categories.map(c => ({ value: c.id, label: c.name }))
            ]" class="w-64"
        />
        
    </div>

    <!-- footer -->
    <template #footer>
        <div class="flex justify-end gap-2">
            <button @click="emit('close')" class="px-4 py-2 bg-gray-200">
                Hủy
            </button>
            <button @click="submit" class="px-4 py-2 bg-green-600 text-white">
                Lưu
            </button>
        </div>
    </template>
</BaseModal>
</template>