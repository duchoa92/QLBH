<script setup>
import { useForm } from '@inertiajs/vue3'
import { closeModal } from '@/Stores/modal'
import { defineProps, defineEmits } from 'vue'
import FloatingInput from '@/Components/UI/FloatingInput.vue'

const props = defineProps({
    category: Object
})

const emit = defineEmits(['close', 'updated'])

const form = useForm({
    id: props.category?.id || null,
    name: props.category?.name || ''
})

const submit = () => {
    if (form.id) {
        form.put(`/categories/${form.id}`, {
            onSuccess: closeModal
        })
    } else {
        form.post('/categories', {
            onSuccess: closeModal
        })
    }
}
</script>

<template>
    <div>
        <FloatingInput v-model="form.name" label="Tên danh mục" class="mb-3 px-1" />

    </div>
    <div class="flex justify-end mt-4">
        <button @click="emit('close')" class="bg-gray-300 text-black px-4 py-2 mr-2">
            Hủy
        </button>
        <button @click="submit" class="bg-green-600 text-white px-4 py-2">
            Lưu
        </button>
    </div>
</template>