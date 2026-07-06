<script setup>
import { useForm } from '@inertiajs/vue3'
import { defineProps, defineEmits } from 'vue'
import FloatingInput from '@/Components/UI/FloatingInput.vue'

const props = defineProps({
    item: Object
})

const emit = defineEmits(['close', 'updated'])

const form = useForm({
    name: props.item?.name || ''
})

const submit = () => {
    if (props.item) {
        form.put(`/categories/${props.item.id}`, {
            onSuccess: () => {
                emit('updated')
                emit('close')
            }
        })
    } else {
        form.post('/categories', {
            onSuccess: () => {
                emit('updated')
                emit('close')
            }
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