<script setup>
import { computed } from 'vue'
import { highlightSegments } from '@/utils/highlight'

// Tô vàng từ khóa của ô "Tìm nhanh toàn hệ thống": hỗ trợ nhiều từ, không dấu, gõ gần đúng.
// (HighlightText.vue là component dùng chung của các bảng, chỉ khớp một cụm liền.)
const props = defineProps({
    text: { type: [String, Number], default: '' },
    keyword: { type: String, default: '' },
})

const segments = computed(() => highlightSegments(props.text, props.keyword))
</script>

<template>
    <template v-for="(segment, index) in segments" :key="index"><mark v-if="segment.hit" class="rounded bg-yellow-200 px-0.5 font-bold text-slate-900">{{ segment.text }}</mark><template v-else>{{ segment.text }}</template></template>
</template>
