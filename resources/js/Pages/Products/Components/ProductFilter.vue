<script setup>
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import FloatingSelect from '@/Components/UI/FloatingSelect.vue'

const props = defineProps({
    filters: Object,
    categories: Array,
    brands: Array
})

const emit = defineEmits(['update:filters'])

const update = (key, value) => {
    emit('update:filters', {
        [key]: value
    })
}
</script>

<template>
<div class="bg-white p-4 rounded-xl shadow border">
    <div class="flex flex-col lg:flex-row gap-3">

        <div class="flex-1">
            <FloatingInput
                :modelValue="filters.search"
                @update:modelValue="v => update('search', v)"
                label="Tìm sản phẩm..."
            />
        </div>

        <div class="flex gap-2">

            <FloatingSelect
                :modelValue="filters.category_id"
                @update:modelValue="v => update('category_id', v)"
                label="Danh mục"
                :options="[{ id: '', name: 'Tất cả' }, ...categories]"
                option-label="name"
                option-value="id"
            />

            <FloatingSelect
                :modelValue="filters.brand_id"
                @update:modelValue="v => update('brand_id', v)"
                label="Thương hiệu"
                :options="[{ id: '', name: 'Tất cả' }, ...brands]"
                option-label="name"
                option-value="id"
            />

        </div>

    </div>
</div>
</template>