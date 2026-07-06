<script setup>
import { useForm } from '@inertiajs/vue3'
import { closeModal } from '@/Stores/modal'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import FloatingSelect from '@/Components/UI/FloatingSelect.vue'

const props = defineProps({
    brand: Object,
    categories: Array
})

const emit = defineEmits(['close', 'updated'])

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
    <div class="flex">
        <FloatingInput v-model="form.name" label="Tên thương hiệu" class="mb-3 px-1" />
        <FloatingSelect
            v-model="form.category_id"
            label="Danh mục"
            :options="props.categories.map(c => ({
                value: c.id,
                label: c.name
            }))"
            class="mb-3 w-1/2 px-1"
        />

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