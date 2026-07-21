<script setup>

import { onMounted, onBeforeUnmount } from 'vue'

const props = defineProps({
    title: String,
    size: {
        type: String,
        default: 'lg' // sm | md | lg | xl
    }
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
    <div class="fixed inset-0 z-50 flex items-center justify-center">
        
        <!-- overlay -->
        <div 
            class="absolute inset-0 bg-black/50"
            @click="$emit('close')"
        ></div>

        <!-- modal -->
        <div class="relative bg-gray-100 rounded-xl shadow-xl w-full mx-4"
            :class="{
                'max-w-md': size === 'sm',
                'max-w-xl': size === 'md',
                'max-w-3xl': size === 'lg',
                'max-w-5xl': size === 'xl'
            }"
        >
            <!-- HEADER -->
            <div class=" flex justify-between items-center p-3 border-b">
                <h2 class="font-semibold text-lg">{{ title }}</h2>
                <button @click="$emit('close')" class="hover:text-red-500">✕</button>
            </div>

            <!-- BODY -->
            <div class="bg-white max-h-[80vh] overflow-y-auto p-3">
                <slot />
            </div>

            <!-- FOOTER -->
            <div v-if="$slots.footer" class="p-3 border-t">
                <slot name="footer" />
            </div>

        </div>
    </div>
</template>