<script setup>
import { Wallet, History } from 'lucide-vue-next'

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
    'show-sale-history',
])
</script>

<template>
    <div class="shrink-0 border-t border-slate-200/80 bg-white p-3 space-y-2.5 rounded-b-2xl shadow-sm">
        <div class="flex justify-between items-center px-1">
            <button
                type="button"
                @click="emit('show-sale-history')"
                class="inline-flex items-center gap-1.5 text-xs text-indigo-600 font-bold hover:text-indigo-700 transition-colors"
            >
                <History class="w-3.5 h-3.5" />
                <span>Lịch sử đơn</span>
            </button>

            <!-- TỔNG TIỀN HÀNG -->
            <div class="text-right">
                <span class="text-xs text-slate-500 font-medium mr-1.5">Tiền hàng:</span>
                <span class="text-base font-extrabold text-indigo-600">
                    {{ $money(grandTotal) }}đ
                </span>
            </div>
        </div>

        <!-- NÚT THANH TOÁN -->
        <button
            :disabled="cart.length === 0 || loading"
            @click="emit('checkout')"
            class="w-full flex items-center justify-center gap-2 h-12 rounded-xl bg-indigo-600 text-white font-bold text-xs uppercase tracking-wider hover:bg-indigo-700 active:scale-[0.98] transition-all shadow-md shadow-indigo-200 disabled:bg-slate-200 disabled:text-slate-400 disabled:shadow-none disabled:cursor-not-allowed"
        >
            <span>Thanh toán</span>
            <Wallet class="w-4 h-4" />
        </button>
    </div>
</template>