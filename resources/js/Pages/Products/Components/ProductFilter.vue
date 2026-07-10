<script setup>
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import FloatingSelect from '@/Components/UI/FloatingSelect.vue'
import { Printer, Trash} from 'lucide-vue-next'

const props = defineProps({
    filters: Object,
    categories: Array,
    brands: Array,
    selectedCount: Number
})

const emit = defineEmits(['update:filters', 'delete', 'print'])

const update = (key, value) => {
    let newFilters = {
        ...props.filters,
        [key]: value ?? ''
    }

    if (key === 'category_id') {
        newFilters.brand_id = ''
    }

    emit('update:filters', newFilters)
}
</script>

<template>
<div class="bg-white p-4 rounded-xl shadow border sticky top-0 z-20">

    <div class="flex flex-wrap items-center gap-3">

        <!-- SEARCH -->
        <div class="w-full lg:flex-1">
            <FloatingInput
                :modelValue="filters.search"
                @update:modelValue="v => update('search', v)"
                label="Tìm sản phẩm"
            />
        </div>

        <!-- ROW 2 (mobile) -->
        <div class="w-full flex items-center gap-2 lg:w-auto">

            <!-- CATEGORY -->
            <div class="flex-1 min-w-[120px]">
                <FloatingSelect
                    :modelValue="filters.category_id"
                    @update:modelValue="v => update('category_id', v)"
                    label="Danh mục"
                    :options="[{ id: '', name: 'Tất cả' }, ...categories]"
                    option-label="name"
                    option-value="id"
                />
            </div>

            <!-- BRAND -->
            <div class="flex-1 min-w-[120px]">
                <FloatingSelect
                    :modelValue="filters.brand_id"
                    @update:modelValue="v => update('brand_id', v)"
                    label="Thương hiệu"
                    :options="[{ id: '', name: 'Tất cả' }, ...brands]"
                    option-label="name"
                    option-value="id"
                />
            </div>

            <!-- BULK ACTION -->
            <transition name="fade-slide">
                <div
                    v-if="selectedCount > 0"
                    class="flex items-center gap-2  rounded whitespace-nowrap"
                >
                    <button
                        @click="$emit('print')"
                        class="flex items-center gap-1 text-blue-600 hover:bg-blue-100 p-2 rounded transition"
                    >
                        <Printer size="16"/><span class="hidden sm:inline"> In IMEI</span>
                    </button>

                    <button
                        @click="$emit('delete')"
                        class="flex items-center gap-1 text-red-600 hover:bg-red-100 p-2 rounded transition"
                    >
                        <Trash size="16"/><span class="hidden sm:inline"> Xóa</span>
                    </button>
                    
                    <span class="text-xs text-gray-500 ml-1">
                        ({{ selectedCount }})
                    </span>
                </div>
            </transition>

        </div>

    </div>

</div>
</template>

<style scoped>
.fade-slide-enter-active,
.fade-slide-leave-active {
    transition: all 0.25s ease;
}

.fade-slide-enter-from {
    opacity: 0;
    transform: translateY(-6px);
}

.fade-slide-enter-to {
    opacity: 1;
    transform: translateY(0);
}

.fade-slide-leave-from {
    opacity: 1;
    transform: translateY(0);
}

.fade-slide-leave-to {
    opacity: 0;
    transform: translateY(-6px);
}
</style>