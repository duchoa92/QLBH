<script setup>
import {
    ref,
} from 'vue'
import PaymentModal from './PaymentModal.vue'

const showPaymentModal = ref(false)

const props = defineProps({

    grandTotal: {
        type: Number,
        default: 0,
    },

    selectedCustomer: {
        type: Object,
        default: null,
    },

    holdSales: {
        type: Array,
        default: () => [],
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


// 
const emit = defineEmits([
    'checkout',
    'open-hold',
    'show-hold-list',
    'show-sale-history',
])

const formatMoney = (value) => {

    return Number(
        value || 0
    ).toLocaleString('vi-VN')
}

</script>

<template>
<!-- khối thanh toán -->
        <div class="shrink-0 border-t border-gray-200 bg-white p-2">
            <div class="flex justify-between items-center mb-2 px-1">
                <div class="flex gap-2">
                    <button @click="emit('open-hold')" class="text-xs text-orange-600 font-bold hover:underline">Lưu (F4)</button>
                    <button @click="emit('show-hold-list')" class="text-xs text-indigo-600 font-bold hover:underline">HĐ đã lưu ({{ holdSales.length }})</button>
                    <button
                        @click="emit('show-sale-history')"
                        class="text-xs text-indigo-600 font-bold hover:underline"
                    >
                        DS hóa đơn
                    </button>
                </div>
                <!-- Tổng tiền -->
                <div class="text-right">
                    <div>
                        <span class="text-sm text-gray-500">
                            Tiền hàng:
                        </span>
                        <span class="font-bold text-blue-700">
                            {{ formatMoney(grandTotal) }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Thanh toán -->
            <button
                :disabled="cart.length === 0 || loading"
                @click="showPaymentModal = true"
                class="w-full h-14 rounded-xl bg-blue-600 text-white font-bold text-sm hover:bg-blue-700 active:scale-[0.98] transition-all shadow-md disabled:bg-gray-300"
            >Thanh toán
            </button>
        </div>

        <PaymentModal
            :show="showPaymentModal"
            :grand-total="grandTotal"
            :customer="selectedCustomer"
            @close="
                showPaymentModal = false
            "
            @confirm="emit('checkout', $event)"
            :cart="cart"
        />
</template>