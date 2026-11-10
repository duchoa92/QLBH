<script setup>
// Một dòng thông tin có biểu tượng: nhãn nhỏ phía trên, giá trị phía dưới; có href thì bấm được (gọi điện, gửi mail...).
defineProps({
    icon: { type: Object, default: null },
    label: { type: String, required: true },
    value: { type: [String, Number], default: '' },
    href: { type: String, default: '' },
})
</script>

<template>
    <div class="flex min-w-0 items-start gap-3 rounded-xl border border-slate-200 bg-white px-3 py-2.5">
        <span v-if="icon" class="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-slate-100 text-slate-500"><component :is="icon" :size="16" /></span>
        <div class="min-w-0 flex-1">
            <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ label }}</div>
            <a v-if="value && href" :href="href" class="mt-0.5 block break-words text-sm font-semibold text-blue-700 hover:underline">{{ value }}</a>
            <div v-else-if="value" class="mt-0.5 whitespace-pre-line break-words text-sm font-semibold text-slate-800"><slot>{{ value }}</slot></div>
            <div v-else class="mt-0.5 text-sm text-slate-400">Chưa cập nhật</div>
        </div>
    </div>
</template>
