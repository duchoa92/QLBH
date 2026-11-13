<script setup>
import { onMounted, onBeforeUnmount, ref } from 'vue'
import { Cloud, CloudOff } from 'lucide-vue-next'

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
            class="relative inline-flex max-w-full items-center justify-center gap-1.5 rounded-lg border border-transparent p-1.5 text-[11px] font-semibold leading-none transition-colors sm:gap-2 sm:rounded-full sm:border-solid sm:px-3 sm:py-1 sm:text-xs"
            :class="online ? 'text-emerald-700 hover:bg-emerald-50 sm:border-emerald-100 sm:bg-emerald-50 sm:hover:bg-emerald-100' : 'bg-rose-50 text-rose-700 hover:bg-rose-100 sm:border-rose-200'"
            :aria-pressed="expanded"
            :aria-label="online ? (expanded ? 'Đang kết nối' : 'POS bán hàng, đang kết nối') : (expanded ? 'Mất kết nối' : 'POS bán hàng offline')"
            @click="expanded = !expanded"
        >
            <Cloud v-if="online" class="h-5 w-5 shrink-0 sm:hidden" aria-hidden="true" />
            <CloudOff v-else class="h-5 w-5 shrink-0 sm:hidden" aria-hidden="true" />
            <span class="absolute right-0.5 top-0.5 h-2 w-2 rounded-full ring-2 ring-white sm:hidden" :class="online ? 'bg-emerald-500' : 'bg-rose-500'" aria-hidden="true"></span>
            <span class="hidden h-2 w-2 shrink-0 rounded-full motion-safe:animate-pulse sm:inline-block" :class="online ? 'bg-emerald-500' : 'bg-rose-500'"></span>
            <span class="hidden truncate sm:inline">{{ expanded ? (online ? 'Đang kết nối' : 'Mất kết nối') : (online ? 'POS Bán hàng' : 'POS bán hàng offline') }}</span>
        </button>
    </div>
</template>
