<script setup>
// Imports
import {
    ref,
} from 'vue'
import { useMoney } from '@/Composables/useMoney'
import { usePaymentKeyboard } from '@/Composables/usePaymentKeyboard'
import { useVietQr } from '@/Composables/useVietQr'
import { usePaymentValidation } from '@/Composables/usePaymentValidation'
import { usePayment } from '@/Composables/usePayment'
import QrPayment from '@/Components/POS/QrPayment.vue'
import PaymentActions from '@/Components/POS/PaymentActions.vue'

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

    paidAmount,

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

// kiem tra thanh toan
const {

    validatePayment,

} = usePaymentValidation()



// Xử lý khi nhập số tiền khách đưa
const rawValue =
    parseMoney(event.target.value)

paidAmount.value =
    rawValue

paidAmountDisplay.value =
    formatMoney(rawValue)


    

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

        <div
    class="bg-white w-[450px] rounded shadow-xl p-5 max-h-[90vh] overflow-y-auto">

            <!-- Title -->
            <div class="text-xl font-bold mb-4">
                Thanh toán
            </div>

            <!-- Total -->
            <div class="mb-4">

                <div class="text-gray-500 text-sm">
                    Tổng tiền
                </div>

                <div class="text-3xl font-bold text-blue-600">
                    {{ formatMoney(Number(props.total || 0)) }} đ
                </div>

            </div>

            <!-- Payment Method -->
            <div class="mb-4">

                <label class="block text-sm mb-1">
                    Phương thức thanh toán
                </label>

                <select
                    v-model="paymentMethod"
                    class="w-full border rounded p-2"
                >
                    <option value="cash">
                        Tiền mặt
                    </option>

                    <option value="bank">
                        Chuyển khoản
                    </option>

                    <option value="card">
                        Thẻ
                    </option>
                </select>

            </div>

            <!-- Paid -->
            <div class="mb-4">

                <label class="block text-sm mb-1">
                    Khách đưa
                </label>

                <input
                    ref="paidInputRef"
                    :value="paidAmountDisplay"
                    @input="handlePaidInput"
                    type="text"
                    inputmode="numeric"
                    class="w-full border rounded p-2 text-lg"
                />

            </div>


            <!-- QR Banking -->
            <QrPayment
                v-if="paymentMethod === 'bank'"
                :url="vietQrUrl"
            />


            <!-- Change -->
            <div class="mb-6">

                <div class="text-sm text-gray-500">
                    Tiền thừa
                </div>

                <div
                    class="text-2xl font-bold"
                    :class="{
                        'text-red-600': changeAmount < 0,
                        'text-green-600': changeAmount >= 0,
                    }"
                >
                    {{ formatMoney(changeAmount) }} đ
                </div>

            </div>

            <!-- Actions -->
            <div
    class="sticky bottom-0 bg-white pt-3 flex justify-end gap-2">

                <button
                    @click="emit('close')"
                    class="px-4 py-2 border rounded"
                >
                    Đóng
                </button>

                <button
                    @click="confirmPayment"
                    class="px-4 py-2 bg-blue-600 text-white rounded"
                >
                    Xác nhận thanh toán
                </button>

            </div>

        </div>

    </div>

</template>