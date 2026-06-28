<script setup>
defineProps({
    show: Boolean,
    product: Object
})

const emit = defineEmits(['close', 'save'])
</script>

<template>
<div v-if="show" class="fixed inset-0 bg-black/40 flex items-center justify-center">

    <div class="bg-white w-full max-w-3xl rounded-xl">

        <div class="p-4 border-b flex justify-between">
            <h2>{{ product?.name }}</h2>
            <button @click="$emit('close')">✕</button>
        </div>

        <div class="p-4">
            <input v-model="product.name" class="input" />
        </div>

        <div class="p-4 border-t flex justify-end">
            <button @click="$emit('save')">Lưu</button>
        </div>

    </div>

</div>
</template>