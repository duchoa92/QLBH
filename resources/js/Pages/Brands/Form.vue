<script setup>
import { useForm } from '@inertiajs/vue3'
import { closeModal } from '@/Stores/modal'
import FloatingInput from '@/Components/UI/FloatingInput.vue'

const props = defineProps({
    brand: Object
})

const form = useForm({
    id: props.brand?.id || null,
    name: props.brand?.name || '',
    category_id: props.brand?.category_id || null
})

const submit = () => {
    if (form.id) {
        form.put(`/brands/${form.id}`, {
            onSuccess: closeModal
        })
    } else {
        form.post('/brands', {
            onSuccess: closeModal
        })
    }
}
</script>

<template>
    <div>
        <FloatingInput v-model="form.name" label="Tên danh mục" />

        <button @click="submit" class="bg-green-600 text-white px-4 py-2">
            Lưu
        </button>
    </div>
</template>