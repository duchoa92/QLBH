<script setup>
defineProps({
    modelValue: { type: String, required: true },
    tabs: { type: Array, required: true }, // [{ key, label, count?, tone? }]
})
defineEmits(['update:modelValue'])
</script>

<template>
    <div class="flex gap-1 overflow-x-auto rounded-xl bg-slate-100 p-1" role="tablist">
        <button
            v-for="tab in tabs"
            :key="tab.key"
            type="button"
            role="tab"
            :aria-selected="modelValue === tab.key"
            class="inline-flex shrink-0 items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-bold transition"
            :class="modelValue === tab.key ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-800'"
            @click="$emit('update:modelValue', tab.key)"
        >
            {{ tab.label }}
            <span
                v-if="tab.count"
                class="rounded-full px-1.5 py-0.5 text-[10px] font-black"
                :class="tab.tone === 'danger' ? 'bg-rose-100 text-rose-700' : 'bg-slate-200 text-slate-600'"
            >{{ tab.count }}</span>
        </button>
    </div>
</template>
