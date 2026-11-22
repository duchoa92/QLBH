<script setup>
// Một nhóm nhỏ trong khối cài đặt: tiêu đề phụ + các tùy chọn liên quan. Đặt trong khung có "divide-y" để các nhóm ngăn cách bằng đường mảnh.
defineProps({
    title: { type: String, default: '' },
})
</script>

<template>
    <div class="space-y-3 p-4">
        <h4 v-if="title" class="text-[11px] font-bold uppercase tracking-wide text-slate-400">{{ title }}</h4>
        <slot />
    </div>
</template>
