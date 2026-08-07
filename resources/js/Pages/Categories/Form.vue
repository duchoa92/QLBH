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
    name: props.category?.name || '',
    attributes: []
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

const addAttribute = () => {
    form.attributes.push({
        name: '',
        options: []
    })
}

const removeAttribute = (index) => {
    form.attributes.splice(index, 1)
}

const addOption = (attr) => {
    attr.options.push('')
}

const removeOption = (attr, index) => {
    attr.options.splice(index, 1)
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

        <!-- ATTRIBUTES-->
        <div class="mt-4">
            <h3 class="font-semibold mb-2">Thuộc tính danh mục</h3>

            <button
                type="button"
                @click="addAttribute"
                class="mb-3 px-3 py-1 bg-blue-500 text-white rounded"
            >
                + Thêm thuộc tính
            </button>

            <div
                v-for="(attr, index) in form.attributes"
                :key="index"
                class="border p-3 rounded mb-3"
            >

                <!-- TÊN ATTRIBUTE -->
                <input
                    v-model="attr.name"
                    placeholder="Tên thuộc tính (VD: Màu sắc, bộ nhớ, phiên bản,...)"
                    class="border p-2 w-full mb-2"
                />

                <!-- OPTIONS -->
                <div
                    v-for="(opt, i) in attr.options"
                    :key="i"
                    class="flex gap-2 mb-2"
                >
                    <input
                        v-model="attr.options[i]"
                        placeholder="Giá trị (VD: Đỏ, xanh, vàng,...)"
                        class="border p-2 flex-1"
                    />

                    <button
                        type="button"
                        @click="removeOption(attr, i)"
                        class="text-red-500"
                    >
                        X
                    </button>
                </div>

                <button
                    type="button"
                    @click="addOption(attr)"
                    class="text-sm text-blue-600"
                >
                    + Thêm giá trị
                </button>

                <div class="mt-2">
                    <button
                        type="button"
                        @click="removeAttribute(index)"
                        class="text-red-600 text-sm"
                    >
                        Xóa thuộc tính
                    </button>
                </div>

            </div>
        </div>
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