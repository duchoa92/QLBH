<script setup>
import { useForm } from '@inertiajs/vue3'
import { closeModal } from '@/Stores/modal'
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
            onSuccess: () => {
                emit('updated')
                closeModal()
            }
        })
    } else {
        form.post('/categories', {
            onSuccess: () => {
                emit('updated')
                closeModal()
            }
        })
    }
}
</script>

<template>
<div class="fixed inset-0 z-50">

    <!-- overlay -->
    <div class="absolute inset-0 bg-black/40" @click="emit('close')"></div>

    <!-- modal -->
    <div class="absolute inset-0 flex items-center justify-center">

        <div class="bg-white w-[500px] rounded-xl shadow-lg p-6">

            <h2 class="text-lg font-bold mb-4">
                {{ form.id ? 'Sửa danh mục' : 'Thêm danh mục' }}
            </h2>

            <FloatingInput
                v-model="form.name"
                label="Tên danh mục"
                class="mb-4"
            />

            <div class="flex justify-end gap-2">
                <button @click="emit('close')" class="bg-gray-300 px-4 py-2 rounded">
                    Hủy
                </button>

                <button @click="submit" class="bg-green-600 text-white px-4 py-2 rounded">
                    Lưu
                </button>
            </div>

        </div>

    </div>
</div>
</template>