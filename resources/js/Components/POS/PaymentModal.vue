<script setup>
// Imports
import {
    computed,
    ref,
    watch,
    onMounted,
    onBeforeUnmount,
    nextTick,
} from 'vue'
import { useMoney } from '@/Composables/useMoney'


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

// Số tiền khách đưa
const paidAmount = ref(0)
// Hiển thị số tiền khách đưa dưới dạng định dạng tiền tệ
const paidAmountDisplay = ref('')

// Phương thức thanh toán: cash, bank, card
const paymentMethod = ref('cash')

// Ref cho input số tiền khách đưa
const paidInputRef = ref(null)

const {

    formatMoney,

    parseMoney,

} = useMoney()


// Xử lý khi nhập số tiền khách đưa
const rawValue =
    parseMoney(event.target.value)

paidAmount.value =
    rawValue

paidAmountDisplay.value =
    formatMoney(rawValue)


// Tạo URL QR Banking
const vietQrUrl = computed(() => {

    const amount = Number(props.total || 0)

    const description = 'POS_PAYMENT'

    const bankBin = '970422'

    const bankAccount = '0123456789'

    return `https://img.vietqr.io/image/${bankBin}-${bankAccount}-compact2.png?amount=${amount}&addInfo=${description}`
})


// Tính tiền thừa
const changeAmount = computed(() => {

    return Number(paidAmount.value) -
        Number(props.total)
})

/*
|--------------------------------------------------------------------------
| Confirm
|--------------------------------------------------------------------------
*/

const confirmPayment = () => {

    if (paidAmount.value < props.total) {

        alert('Khách đưa chưa đủ tiền')

        return
    }

    emit('confirm', {

        paid_amount: paidAmount.value,

        payment_method: paymentMethod.value,
    })
}

/*
|--------------------------------------------------------------------------
| Reset
|--------------------------------------------------------------------------
*/

watch(
    () => props.show,
    (value) => {

        if (value) {

            paidAmount.value = props.total

            paidAmountDisplay.value =
                formatMoney(props.total)

            nextTick(() => {

                paidInputRef.value?.focus()

                paidInputRef.value?.select()
            })
        }
    }
)

/*
|--------------------------------------------------------------------------
| Keyboard
|--------------------------------------------------------------------------
*/

const handleKeydown = (event) => {

    if (!props.show) {

        return
    }

    // ESC
    if (event.key === 'Escape') {

        emit('close')
    }

    // ENTER
    if (event.key === 'Enter') {

        confirmPayment()
    }
}

onMounted(() => {

    window.addEventListener(
        'keydown',
        handleKeydown
    )
})

onBeforeUnmount(() => {

    window.removeEventListener(
        'keydown',
        handleKeydown
    )
})

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
            <div
                v-if="paymentMethod === 'bank'"
                class="mb-4"
            >

                <div class="text-sm text-gray-500 mb-2">
                    Quét QR để thanh toán
                </div>

                <img
                    :src="vietQrUrl"
                    class="w-56 mx-auto border rounded-lg shadow"
                />

            </div>


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