<script setup>
import { computed } from 'vue'

const props = defineProps({ status: { type: String, required: true }, waitingForParts: { type: Boolean, default: false }, waitingMode: { type: String, default: 'repair_now' }, canOpenRepair: { type: Boolean, default: false }, canOpenComplete: { type: Boolean, default: false }, workflowStage: { type: String, default: 'progress' } })
const emit = defineEmits(['select'])

const steps = [
    { value: 'pending', label: 'Tiếp nhận' },
    { value: 'repairing', label: 'Đang sửa' },
    { value: 'done', label: 'Hoàn tất sửa' },
    { value: 'returned', label: 'Đã trả khách' },
]
const activeIndex = computed(() => props.workflowStage === 'complete' && props.status !== 'done'
    ? 2
    : Math.max(0, steps.findIndex((step) => step.value === props.status)))
const isSelectable = (index) => index <= activeIndex.value || (index === 1 && props.canOpenRepair) || (index === 2 && props.canOpenComplete)
</script>

<template>
    <div v-if="status === 'cancelled'" class="inline-flex items-center gap-1.5 rounded-full border border-rose-200 bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700">
        <span class="h-2 w-2 rounded-full bg-rose-500" /> Hủy
    </div>
    <div v-else class="w-full overflow-x-auto py-1" :aria-label="`Tiến trình: ${steps[activeIndex]?.label}`">
        <div class="flex w-full min-w-0 items-start justify-between gap-0 px-0 sm:min-w-max sm:items-center sm:justify-center sm:gap-3 sm:px-1">
            <template v-for="(step, index) in steps" :key="step.value">
                <button type="button" class="group inline-flex min-w-0 flex-1 flex-col items-center gap-1 rounded-md px-0.5 text-center focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 disabled:cursor-default sm:flex-none sm:flex-row sm:gap-2 sm:px-0 sm:text-left"
                    :disabled="!isSelectable(index)" :aria-current="index === activeIndex ? 'step' : undefined" :aria-label="`Chuyển đến mốc ${step.label}`" @click="emit('select', step.value)">
                    <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full border text-[10px] font-bold transition-colors sm:h-8 sm:w-8 sm:text-sm"
                        :class="index < activeIndex ? 'border-emerald-600 bg-emerald-600 text-white shadow-sm shadow-emerald-200' : index === activeIndex ? 'border-blue-600 bg-blue-600 text-white shadow-sm shadow-blue-200' : 'border-slate-300 bg-slate-100 text-slate-400'">
                        <svg v-if="index < activeIndex" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4"><path fill-rule="evenodd" d="M16.704 5.29a1 1 0 010 1.42l-7.25 7.25a1 1 0 01-1.415 0l-3.25-3.25a1 1 0 011.414-1.42l2.543 2.544 6.543-6.544a1 1 0 011.415 0z" clip-rule="evenodd" /></svg>
                        <span v-else>{{ index + 1 }}</span>
                    </span>
                    <span class="max-w-full whitespace-normal text-[9px] font-semibold leading-tight transition-colors sm:whitespace-nowrap sm:text-xs"
                        :class="index < activeIndex ? 'text-emerald-700' : index === activeIndex ? 'text-slate-900' : 'text-slate-400 group-hover:text-slate-600'">{{ index === 1 && waitingForParts ? (waitingMode === 'wait_repair' ? 'Tạm chờ sửa' : 'Chờ linh kiện') : step.label }}</span>
                </button>
                <span v-if="index < steps.length - 1" class="mt-3 hidden h-0.5 w-5 shrink-0 rounded-full sm:block sm:w-8" :class="index < activeIndex ? 'bg-emerald-500' : 'bg-slate-200'" aria-hidden="true" />
            </template>
        </div>
    </div>
</template>
