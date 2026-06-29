<script setup>
import Create from '../Create.vue'

defineProps({
    show: Boolean,
    categories: Array,
    brands: Array
})

const emit = defineEmits(['close'])
</script>

<template>
<div v-if="show" class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center">

    <div class="bg-white w-[900px] max-h-[90vh] overflow-y-auto rounded-xl shadow-lg">

        <!-- HEADER -->
        <div class="flex justify-between items-center p-4 border-b">
            <h2 class="text-lg font-bold">Thêm sản phẩm</h2>
            <button @click="emit('close')" class="text-gray-500">✕</button>
        </div>

        <!-- CONTENT (REUSE 100% Create.vue) -->
        <Create
            :categories="categories"
            :brands="brands"
            :isModal="true"
            @success="emit('close')"
        />

    </div>

</div>
</template>