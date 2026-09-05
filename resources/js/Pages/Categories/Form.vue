<script setup>
import { ref, watch, computed, h } from 'vue'
import { useForm } from '@inertiajs/vue3'
import BaseModal from '@/Components/UI/BaseModal.vue'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import TagBadge from '@/Components/UI/TagBadge.vue'

const props = defineProps({
    category: Object,
    title: String
})
const attrInput = ref('')

const emit = defineEmits(['close', 'updated'])

const form = useForm({
    id: null,
    name: '',
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

// Tạo hàm Tag
const addAttributeTag = () => {
    const values = attrInput.value.split(',')

    values.forEach(v => {
        const name = v.trim()
        if (!name) return

        // tránh trùng
        const exists = form.attributes.some(a => a.name === name)
        if (!exists) {
            form.attributes.push({
                name,
                options: []
            })
        }
    })

    attrInput.value = ''
}

// Bắt sự kiện Enter hoặc dấu phẩy
const handleAttrKey = (e) => {
    if (e.key === 'Enter' || e.key === ',') {
        e.preventDefault()
        addAttributeTag()
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

watch(() => props.category, (c) => {

    if (!c) {
        form.id = null
        form.name = ''
        form.attributes = []
        return
    }

    form.id = c.id ?? null
    form.name = c.name ?? ''

    form.attributes = (c.attributes ?? []).map(attr => ({
        id: attr.id ?? null,
        name: attr.name ?? '',
        options: [...(attr.options ?? [])]
    }))

}, {
    immediate: true
})

/* watch(() => props.category, (c) => {
    console.log('CATEGORY:', c)
}, { immediate: true }) */

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
            :error="form.errors.name"
        />

        <!-- ATTRIBUTES-->
        <div class="mt-4">
            <FloatingInput
                v-model="attrInput"
                @keydown="handleAttrKey"
                @blur="addAttributeTag"
                label="Nhập thuộc tính"
            />
            <h4 class="text-sm text-gray-500 mx-2 my-1">Ví dụ: Màu sắc, Bộ nhớ, Dung lượng,...</h4>

           


            <!-- TAG LIST -->
            <div
                :class="[
                    'rounded-lg p-2 my-2 flex flex-wrap gap-2',
                    form.attributes.length
                        ? 'border border-gray-300'
                        : 'border border-transparent'
                ]"
            >

                <TagBadge
                    v-for="(attr, index) in form.attributes"
                    :key="index"
                    :label="attr.name"
                    removable
                    @remove="removeAttribute(index)"
                    size="lg"
                    color="blue"
                />

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