<script setup>
import { useForm } from '@inertiajs/vue3'
import { defineProps, defineEmits } from 'vue'

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
        <FloatingInput v-model="form.name" label="Tên danh mục" />

        <button @click="submit" class="bg-green-600 text-white px-4 py-2">
            Lưu
        </button>
    </div>
</template>