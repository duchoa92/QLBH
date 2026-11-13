<script setup>
// Công tắc bật/tắt dùng chung cho các mục Cài đặt, để mọi tùy chọn đúng/sai có cùng kiểu hiển thị và cách thao tác.
defineProps({
    modelValue: { type: Boolean, default: false },
    title: { type: String, required: true },
    description: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
})
defineEmits(['update:modelValue'])
</script>

<template>
    <label class="relative flex items-center justify-between gap-4" :class="disabled ? 'cursor-not-allowed opacity-60' : 'cursor-pointer'">
        <span class="min-w-0">
            <span class="block text-sm font-semibold text-slate-800">{{ title }}</span>
            <span v-if="description" class="mt-0.5 block text-xs leading-5 text-slate-500">{{ description }}</span>
        </span>
        <input
            type="checkbox"
            role="switch"
            class="peer sr-only"
            :checked="modelValue"
            :disabled="disabled"
            :aria-label="title"
            @change="$emit('update:modelValue', $event.target.checked)"
        >
        <span class="relative inline-flex h-6 w-11 shrink-0 rounded-full bg-slate-300 transition peer-checked:bg-emerald-600 peer-focus-visible:ring-2 peer-focus-visible:ring-emerald-500 peer-focus-visible:ring-offset-2 after:absolute after:left-1 after:top-1 after:h-4 after:w-4 after:rounded-full after:bg-white after:transition-transform peer-checked:after:translate-x-5"></span>
    </label>
</template>
