<script setup>
import { ref } from 'vue'
import { ChevronDown } from 'lucide-vue-next'

// Khối cài đặt có thể thu gọn/mở rộng. Dùng cho các mục con để modal dài vẫn gọn, người dùng tự mở phần cần chỉnh.
const props = defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
    defaultOpen: { type: Boolean, default: true },
})

const open = ref(props.defaultOpen)
</script>

<template>
    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <button
            type="button"
            class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left transition hover:bg-slate-50"
            :aria-expanded="open"
            @click="open = !open"
        >
            <span class="min-w-0">
                <span class="block text-sm font-bold text-slate-900">{{ title }}</span>
                <span v-if="description || $slots.description" class="mt-0.5 block text-xs font-medium text-slate-500">
                    <slot name="description">{{ description }}</slot>
                </span>
            </span>
            <ChevronDown class="h-4 w-4 shrink-0 text-slate-400 transition-transform" :class="open ? 'rotate-180' : ''" aria-hidden="true" />
        </button>
        <div v-show="open" class="border-t border-slate-100">
            <slot />
        </div>
    </section>
</template>
