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
    <div class="fixed inset-0 z-50 flex items-center justify-center px-4 py-6">
        
        <!-- overlay -->
        <div 
            class="absolute inset-0 bg-slate-950/45 backdrop-blur-[2px]"
            @click="$emit('close')"
        ></div>

        <!-- modal -->
        <div class="relative flex max-h-[92vh] w-full flex-col overflow-hidden rounded-xl bg-white shadow-2xl ring-1 ring-slate-900/10"
            :class="{
                'max-w-md': size === 'sm',
                'max-w-xl': size === 'md',
                'max-w-3xl': size === 'lg',
                'max-w-5xl': size === 'xl'
            }"
        >
            <!-- HEADER -->
            <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-4 py-3">
                <h2 class="text-base font-bold text-slate-900">{{ title }}</h2>
                <button
                    type="button"
                    @click="$emit('close')"
                    class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:bg-white hover:text-red-500"
                    aria-label="Đóng"
                >
                    ✕
                </button>
            </div>

            <!-- BODY -->
            <div class="min-h-0 flex-1 overflow-y-auto bg-white p-4">
                <slot />
            </div>

            <!-- FOOTER -->
            <div v-if="$slots.footer" class="border-t border-slate-200 bg-slate-50 px-4 py-3">
                <slot name="footer" />
            </div>

        </div>
    </div>
</template>
