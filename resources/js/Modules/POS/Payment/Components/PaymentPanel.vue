<script setup>
import {
    ref,
    computed,
    onMounted,
    onBeforeUnmount,
} from 'vue'
import PaymentMethodSelect from './PaymentMethodSelect.vue'
import { useEventBus } from '@/Composables/useEventBus'


const {
    onEvent,
    offEvent,
} = useEventBus()

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

// Reset Form
const resetPosForm = () => {

    note.value = ''

    paidAmount.value = ''

    payOldDebt.value = false

    paymentMethod.value = 'cash'
}

onMounted(() => {

    onEvent(
        'pos:reset',
        resetPosForm
    )
})

onBeforeUnmount(() => {

    offEvent(
        'pos:reset',
        resetPosForm
    )
})


const note = ref('')
// Phương thức thanh toán
const paymentMethod = ref('cash')
// tiền khách đưa
const paidAmount = ref('')
// thanh toán nợ cũ
const payOldDebt = ref(false)

// Số tiền thừa trả khách
const balanceAmount = computed(() => {

    const paid =
        Number(
            paidAmount.value
        ) || 0

    return (
        paid -
        totalNeedToPay.value
    )
})

// Tổng tiền cần thanh toán (bao gồm nợ cũ nếu có)
const totalNeedToPay = computed(() => {

    const debt = payOldDebt.value
        ? Number(
            props.selectedCustomer?.debt_balance || 0
        )
        : 0

    return (
        Number(props.grandTotal)
        + debt
    )
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

                    <!-- Thanh toán nợ cũ -->
                    <div
                        v-if="
                            selectedCustomer &&
                            Number(selectedCustomer.debt_balance) > 0
                        "
                        class="mt-1 text-right"
                    >

                        <label
                            class="inline-flex items-center gap-2 text-sm text-red-600 font-medium cursor-pointer"
                        >

                            <input
                                v-model="payOldDebt"
                                type="checkbox"
                            >

                            Thanh toán nợ cũ:
                            {{
                                formatMoney(
                                    selectedCustomer.debt_balance
                                )
                            }}

                        </label>

                    </div>
                    <div
                        class="font-bold text-lg text-green-600"
                    >
                        Cần thu:
                        {{
                            formatMoney(
                                totalNeedToPay
                            )
                        }}
                    </div>
                </div>
            </div>

            <!-- Phương thức thanh toán -->
           <div class="space-y-2 mb-3">
                <div class="grid grid-cols-2 gap-3">
                    <PaymentMethodSelect v-model="paymentMethod" class="h-12 text-sm rounded-xl border border-gray-300" />
                    <input 
                        v-model="paidAmount" 
                        type="number" 
                        placeholder="Khách đưa" 
                        class="h-12 px-4 border border-gray-300 rounded-xl text-lg font-bold shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all" 
                    />
                </div>
                
                <input 
                    v-model="note" 
                    type="text" 
                    placeholder="Ghi chú đơn hàng..." 
                    class="w-full h-10 px-4 border border-gray-300 rounded-xl text-sm shadow-sm focus:ring-2 focus:ring-blue-500 transition-all" 
                />
            </div>

            
            <!-- Thanh toán -->
            <button
                :disabled="cart.length === 0 || loading"
                @click="emit('checkout', { 
                    note: note,
                    payment_method: paymentMethod,
                    paid_amount: paidAmount,
                    pay_old_debt: payOldDebt,

                    })"
                class="w-full h-14 rounded-xl bg-blue-600 text-white font-bold text-sm hover:bg-blue-700 active:scale-[0.98] transition-all shadow-md disabled:bg-gray-300"
            >
                {{ loading ? 'ĐANG XỬ LÝ...' : `THANH TOÁN (${formatMoney(totalNeedToPay)})` }}
                <span
                    v-if="balanceAmount > 0"
                    class="ml-2 text-green-200 font-normal"
                >
                    Thừa:
                    {{ formatMoney(balanceAmount) }}
                </span>

                <span
                    v-else-if="balanceAmount < 0"
                    class="ml-2 text-red-200 font-normal"
                >
                    Thiếu:
                    {{ formatMoney(
                        Math.abs(balanceAmount)
                    ) }}
                </span>
            </button>
        </div>
</template>