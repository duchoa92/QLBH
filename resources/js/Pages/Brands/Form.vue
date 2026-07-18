<script setup>
import { useForm } from '@inertiajs/vue3'
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
<div class="fixed inset-0 z-50 flex items-center justify-center">

    <!-- overlay -->
    <div class="absolute inset-0 bg-black/40" @click="emit('close')"></div>

    <!-- modal -->
    <div class="bg-white w-[500px] rounded-xl shadow-lg relative z-10 overflow-hidden">

        <!-- 🔥 HEADER -->
        <div class="flex items-center justify-between px-5 py-3 border-b bg-gray-50">
            <h2 class="text-lg font-semibold">
                {{ form.id ? 'Sửa thương hiệu' : 'Thêm thương hiệu' }}
            </h2>
            <button @click="emit('close')" class="text-gray-400 hover:text-black text-xl">
                ✕
            </button>
        </div>

        <!-- 🔥 CONTENT -->
        <div class="p-5 space-y-4">
            <div>
                <FloatingInput v-model="form.name" label="Tên thương hiệu" class="w-full" />
                <p v-if="form.errors.name" class="text-xs text-red-500 mt-1">{{ form.errors.name }}</p>
            </div>

            <div>
                <FloatingSelect
                    v-model="form.category_id"
                    label="Danh mục"
                    :options="props.categories.map(c => ({
                        value: c.id,
                        label: c.name
                    }))"
                    class="w-full"
                />
                <p v-if="form.errors.category_id" class="text-xs text-red-500 mt-1">{{ form.errors.category_id }}</p>
            </div>
        </div>

        <!-- 🔥 FOOTER -->
        <div class="flex justify-end gap-2 px-5 py-3 border-t bg-gray-50">
            <button @click="emit('close')" class="bg-gray-100 px-4 py-2 rounded">
                Hủy
            </button>
            <button @click="submit" :disabled="form.processing"
                class="bg-green-600 text-white px-4 py-2 rounded disabled:opacity-50">
                {{ form.processing ? 'Đang lưu...' : 'Lưu dữ liệu' }}
            </button>
        </div>

    </div>
</div>
</template>