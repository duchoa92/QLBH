<script setup>
defineProps({
    grand_total: {
        type: Number,
        default: 0,
    },
    changeAmount: {
        type: Number,
        default: 0,
    },
    formatMoney: {
        type: Function,
        required: true,
    },
})
</script>

<template>
    <div class="rounded-xl border border-slate-200/80 bg-slate-50/60 p-4 space-y-3">
        <!-- TỔNG TIỀN -->
        <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Tổng tiền</span>
            <span class="text-xl font-black text-indigo-600">
                {{ formatMoney(grand_total) }}đ
            </span>
        </div>

        <!-- TIỀN THỪA KHÁCH -->
        <div class="flex items-center justify-between pt-2 border-t border-slate-200/60">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Tiền thừa</span>
            <span
                class="text-lg font-extrabold"
                :class="changeAmount < 0 ? 'text-rose-600' : 'text-emerald-600'"
            >
                {{ formatMoney(changeAmount) }}đ
            </span>
        </div>
    </div>
</template>