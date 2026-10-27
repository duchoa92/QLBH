<script setup>
import { onMounted, onBeforeUnmount, ref } from 'vue'

const online = ref(typeof navigator === 'undefined' ? true : navigator.onLine)
const expanded = ref(false)
const updateOnline = () => { online.value = navigator.onLine }

onMounted(() => {
    updateOnline()
    window.addEventListener('online', updateOnline)
    window.addEventListener('offline', updateOnline)
})
onBeforeUnmount(() => {
    window.removeEventListener('online', updateOnline)
    window.removeEventListener('offline', updateOnline)
})
</script>

<template>
    <div class="flex min-w-0 max-w-[44%] shrink-0 flex-col items-end gap-1 sm:max-w-none" role="status" aria-live="polite">
        <button
            type="button"
            class="inline-flex max-w-full items-center gap-1.5 rounded-full border px-2 py-1 text-[11px] font-semibold leading-none transition-colors sm:gap-2 sm:px-3 sm:text-xs"
            :class="online ? 'border-emerald-100 bg-emerald-50 text-emerald-700 hover:bg-emerald-100' : 'border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100'"
            :aria-pressed="expanded"
            :aria-label="online ? (expanded ? 'Đang kết nối' : 'POS bán hàng, đang kết nối') : (expanded ? 'Mất kết nối' : 'POS bán hàng offline')"
            @click="expanded = !expanded"
        >
            <span class="h-2 w-2 shrink-0 rounded-full motion-safe:animate-pulse" :class="online ? 'bg-emerald-500' : 'bg-rose-500'"></span>
            <span class="truncate">{{ expanded ? (online ? 'Đang kết nối' : 'Mất kết nối') : (online ? 'POS Bán hàng' : 'POS bán hàng offline') }}</span>
        </button>
    </div>
</template>
