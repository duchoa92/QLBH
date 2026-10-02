<script setup>
import { computed } from 'vue'

const props = defineProps({ status: { type: String, required: true } })

const steps = [
    { value: 'pending', label: 'Tiếp nhận' },
    { value: 'repairing', label: 'Đang sửa' },
    { value: 'done', label: 'Hoàn tất sửa' },
    { value: 'returned', label: 'Đã trả khách' },
]
const activeIndex = computed(() => Math.max(0, steps.findIndex((step) => step.value === props.status)))
const progressWidth = computed(() => `${(activeIndex.value / (steps.length - 1)) * 100}%`)
</script>

<template>
    <div v-if="status === 'cancelled'" class="inline-flex items-center gap-1.5 rounded-full border border-rose-200 bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700">
        <span class="h-2 w-2 rounded-full bg-rose-500" /> Hủy
    </div>
    <div v-else class="w-full max-w-xl" :aria-label="`Tiến trình: ${steps[activeIndex]?.label}`">
        <div class="relative flex items-center justify-between">
            <div class="absolute left-2 right-2 top-1/2 h-1 -translate-y-1/2 rounded-full bg-slate-200" />
            <div class="absolute left-2 top-1/2 h-1 -translate-y-1/2 rounded-full bg-gradient-to-r from-blue-500 to-emerald-500 transition-[width] duration-700 ease-out" :style="{ width: `calc(${progressWidth} - ${(activeIndex / (steps.length - 1)) * 16}px)` }" />
            <div v-for="(step, index) in steps" :key="step.value" class="relative z-10 flex flex-col items-center">
                <span class="grid h-5 w-5 place-items-center rounded-full border-2 transition-all duration-500"
                    :class="index <= activeIndex ? 'scale-105 border-emerald-500 bg-emerald-500 text-white shadow-sm shadow-emerald-200' : 'border-slate-300 bg-white text-transparent'">
                    <svg v-if="index < activeIndex" viewBox="0 0 20 20" fill="currentColor" class="h-3 w-3"><path fill-rule="evenodd" d="M16.704 5.29a1 1 0 010 1.42l-7.25 7.25a1 1 0 01-1.415 0l-3.25-3.25a1 1 0 011.414-1.42l2.543 2.544 6.543-6.544a1 1 0 011.415 0z" clip-rule="evenodd" /></svg>
                    <span v-else class="h-1.5 w-1.5 rounded-full bg-current" />
                </span>
                <span class="mt-1 whitespace-nowrap text-[10px] font-semibold leading-tight"
                    :class="index === activeIndex ? 'text-emerald-700' : index < activeIndex ? 'text-slate-600' : 'text-slate-400'">{{ step.label }}</span>
            </div>
        </div>
    </div>
</template>
