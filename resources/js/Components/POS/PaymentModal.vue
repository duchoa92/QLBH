<script setup>
import {
    ref,
} from 'vue'
import { usePayment } from '@/Modules/POS/Payment/Composables/usePayment'
import { usePaymentKeyboard } from '@/Modules/POS/Payment/Composables/usePaymentKeyboard'
import QrPayment from '@/Modules/POS/Payment/Components/QrPayment.vue'
import PaymentActions from '@/Modules/POS/Payment/Components/PaymentActions.vue'
import PaymentSummary from '@/Modules/POS/Payment/Components/PaymentSummary.vue'
import PaymentAmountInput from '@/Modules/POS/Payment/Components/PaymentAmountInput.vue'
import PaymentMethodSelect from '@/Modules/POS/Payment/Components/PaymentMethodSelect.vue'


// Props
const props = defineProps({

    show: {
        type: Boolean,
        default: false,
    },

    total: {
        type: Number,
        default: 0,
    },
})

// Định nghĩa các sự kiện: close, confirm
const emit = defineEmits([
    'close',
    'confirm',
])


// Ref cho input số tiền khách đưa
const paidInputRef = ref(null)


// Payment composables
const {

    loading,

    paidAmountDisplay,

    paymentMethod,

    vietQrUrl,

    changeAmount,

    formatMoney,

    handlePaidInput,

    confirmPayment,

} = usePayment(

    props,

    emit,

    paidInputRef,
)


    

// Phím tắt
usePaymentKeyboard(

    props,

    emit,

    confirmPayment,
)




</script>

<template>

    <div
        v-if="show"
        class="fixed inset-0 bg-black/40 flex items-center justify-center z-50"
    >

        <div class="bg-white w-[450px] rounded shadow-xl p-5 max-h-[90vh] overflow-y-auto">

            <!-- Title -->
            <div class="text-xl font-bold mb-4">
                Thanh toán
            </div>

            <!-- Payment Summary -->
            <PaymentSummary

                :total="
                    Number(props.total || 0)
                "

                :change-amount="
                    changeAmount
                "

                :format-money="
                    formatMoney
                "
            />
            

            <!-- Payment Method -->
            <PaymentMethodSelect
                :model-value="paymentMethod"
                @update:model-value="paymentMethod = $event"
            />

            <!-- Paid -->
            <PaymentAmountInput
                :model-value="paidAmountDisplay"
                :input-ref="paidInputRef"
                @update:model-value="handlePaidInput"
            />


            <!-- QR Banking -->
            <QrPayment
                v-if="paymentMethod === 'bank'"
                :url="vietQrUrl"
            />


            <!-- Change -->
            

            <!-- Actions -->
           <PaymentActions
                :loading="loading"
                @close="emit('close')"
                @confirm="confirmPayment"
            />

        </div>

    </div>

</template>