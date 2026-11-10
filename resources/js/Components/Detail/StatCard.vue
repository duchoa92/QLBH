<script setup>
defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], default: '—' },
    hint: { type: String, default: '' },
    tone: { type: String, default: 'default' },
    icon: { type: Object, default: null },
})

const tones = {
    default: { box: 'border-slate-200 bg-white', icon: 'bg-slate-100 text-slate-600', value: 'text-slate-900' },
    danger: { box: 'border-rose-200 bg-rose-50/60', icon: 'bg-rose-100 text-rose-600', value: 'text-rose-700' },
    success: { box: 'border-emerald-200 bg-emerald-50/60', icon: 'bg-emerald-100 text-emerald-600', value: 'text-emerald-700' },
    info: { box: 'border-blue-200 bg-blue-50/60', icon: 'bg-blue-100 text-blue-600', value: 'text-blue-700' },
    warning: { box: 'border-amber-200 bg-amber-50/60', icon: 'bg-amber-100 text-amber-600', value: 'text-amber-700' },
}
</script>

<template>
    <div class="min-w-0 rounded-xl border p-3" :class="tones[tone]?.box || tones.default.box">
        <div class="flex items-center gap-2">
            <span v-if="icon" class="grid h-7 w-7 shrink-0 place-items-center rounded-lg" :class="tones[tone]?.icon || tones.default.icon"><component :is="icon" :size="15" /></span>
            <span class="truncate text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ label }}</span>
        </div>
        <div class="mt-1.5 truncate text-lg font-black leading-tight" :class="tones[tone]?.value || tones.default.value">{{ value }}</div>
        <div v-if="hint" class="mt-0.5 truncate text-[11px] text-slate-500">{{ hint }}</div>
    </div>
</template>
