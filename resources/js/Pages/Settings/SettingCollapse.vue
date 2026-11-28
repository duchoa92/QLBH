<script setup>
import { ref } from 'vue'
import { ChevronDown } from 'lucide-vue-next'

// Khối cài đặt có thể thu gọn/mở rộng. Dùng cho các mục con để modal dài vẫn gọn, người dùng tự mở phần cần chỉnh.
// Chế độ `switchable`: công tắc bật/tắt thay chỗ mũi tên ở tiêu đề (v-model); bật thì nội dung hiện, tắt thì ẩn.
const props = defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
    defaultOpen: { type: Boolean, default: true },
    switchable: { type: Boolean, default: false },
    modelValue: { type: Boolean, default: false },
})
defineEmits(['update:modelValue'])

const open = ref(props.defaultOpen)
</script>

<template>
    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <label
            v-if="switchable"
            class="relative flex w-full cursor-pointer items-center justify-between gap-3 px-4 py-3 transition hover:bg-slate-50"
        >
            <span class="min-w-0">
                <span class="block text-sm font-bold text-slate-900">{{ title }}</span>
                <span v-if="description" class="mt-0.5 block text-xs font-medium text-slate-500">{{ description }}</span>
            </span>
            <input
                type="checkbox"
                role="switch"
                class="peer sr-only"
                :checked="modelValue"
                :aria-label="title"
                @change="$emit('update:modelValue', $event.target.checked)"
            >
            <span class="relative inline-flex h-6 w-11 shrink-0 rounded-full bg-slate-300 transition peer-checked:bg-emerald-600 peer-focus-visible:ring-2 peer-focus-visible:ring-emerald-500 peer-focus-visible:ring-offset-2 after:absolute after:left-1 after:top-1 after:h-4 after:w-4 after:rounded-full after:bg-white after:transition-transform peer-checked:after:translate-x-5"></span>
        </label>
        <button
            v-else
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
        <div v-show="switchable ? modelValue : open" class="border-t border-slate-100">
            <slot />
        </div>
    </section>
</template>
