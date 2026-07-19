<script setup>

import { onMounted, onBeforeUnmount } from 'vue'

const props = defineProps({
    title: String
})

const emit = defineEmits(['close', 'updated'])

const handleEsc = (e) => {
    if (e.key === 'Escape') {
        emit('close')
    }
}

onMounted(() => {
    window.addEventListener('keydown', handleEsc)
})

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleEsc)
})

</script>

<template>
<div class="fixed inset-0 flex items-center justify-center z-50">

    <!-- overlay click -->
    <div class="absolute inset-0 bg-black/40" @click="emit('close')"></div>

    <div class="bg-white w-[500px] rounded-xl shadow-lg relative z-10 overflow-hidden">

        <!-- header -->
        <div class="flex justify-between items-center px-5 py-3 border-b bg-gray-50">
            <h2 class="font-semibold">{{ title }}</h2>
            <button @click="emit('close')">✕</button>
        </div>

        <!-- content -->
        <div class="p-5">
            <slot />
        </div>

        <!-- footer -->
        <div class="px-5 py-3 border-t bg-gray-50">
            <slot name="footer" />
        </div>

    </div>
</div>
</template>