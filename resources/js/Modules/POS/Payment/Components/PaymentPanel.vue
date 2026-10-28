<script setup>
import { Wallet } from 'lucide-vue-next'

const props = defineProps({
    grandTotal: {
        type: Number,
        default: 0,
    },
    selectedCustomer: {
        type: Object,
        default: null,
    },
    loading: {
        type: Boolean,
        default: false,
    },
    cart: {
        type: Array,
        default: () => [],
    },
})

const emit = defineEmits([
    'checkout',
])
</script>

<template>
    <div class="shrink-0 bg-white px-1">
        <button
            :disabled="cart.length === 0 || loading"
            @click="emit('checkout')"
            class="w-full flex items-center justify-center gap-2 h-12 rounded-xl bg-blue-600 text-white font-bold text-xs uppercase tracking-wider hover:bg-blue-700 active:scale-[0.98] transition-all shadow-md shadow-blue-200 disabled:bg-slate-200 disabled:text-slate-400 disabled:shadow-none disabled:cursor-not-allowed"
        >
            <Wallet class="w-4 h-4" />
            <span>Thanh toán</span>
            <span class="rounded-md bg-white/15 px-2 py-1 text-xs font-extrabold normal-case tracking-normal">{{ $money(grandTotal) }}</span>
        </button>
    </div>
</template>
