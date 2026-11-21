<script setup>
import SearchHighlight from '@/Components/UI/SearchHighlight.vue'

// Danh sách kết quả tìm kiếm toàn hệ thống (dùng chung cho ô tìm của trang và cửa sổ Ctrl+K).
defineProps({
    groups: { type: Array, default: () => [] },
    total: { type: Number, default: 0 },
    loading: { type: Boolean, default: false },
    keyword: { type: String, default: '' },
    activeIndex: { type: Number, default: -1 },
})
const emit = defineEmits(['select', 'hover'])
</script>

<template>
    <div>
        <div v-if="loading && !total" class="px-4 py-4 text-center text-xs font-medium text-slate-500">Đang tìm trong dữ liệu...</div>
        <div v-else-if="!total" class="px-4 py-4 text-center text-xs font-medium text-slate-500">Không tìm thấy kết quả phù hợp.</div>
        <div v-else>
            <div class="px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ total }} kết quả phù hợp</div>
            <section v-for="group in groups" :key="group.type" class="mb-1 last:mb-0">
                <div class="sticky top-0 z-10 flex items-center justify-between rounded-lg bg-slate-100/95 px-3 py-1 text-[11px] font-extrabold uppercase tracking-wide text-slate-600 backdrop-blur">
                    <span>{{ group.type }}</span>
                    <span class="rounded-full bg-white px-1.5 text-[10px] font-bold text-slate-500">{{ group.items.length }}</span>
                </div>
                <div class="mt-0.5 space-y-0.5">
                    <a
                        v-for="{ result, index } in group.items"
                        :key="`${result.type}-${result.record.id}-${index}`"
                        href="#"
                        class="flex items-center gap-2.5 rounded-xl px-2.5 py-2 transition hover:bg-blue-50/80"
                        :class="{ 'bg-blue-50/80': activeIndex === index }"
                        @click.prevent="emit('select', result)"
                        @mouseenter="emit('hover', index)"
                    >
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-bold leading-tight text-slate-800"><SearchHighlight :text="result.label" :keyword="keyword" /></span>
                            <span v-if="result.detail" class="mt-0.5 block truncate text-xs text-slate-500"><SearchHighlight :text="result.detail" :keyword="keyword" /></span>
                        </span>
                    </a>
                </div>
            </section>
        </div>
    </div>
</template>
