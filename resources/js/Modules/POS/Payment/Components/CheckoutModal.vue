<<script setup>

import { ref, watch, computed } from 'vue'
import PaymentMethodSelect from './PaymentMethodSelect.vue'

const props = defineProps({

    show: Boolean,

    grandTotal: Number,

    selectedCustomer: Object,
})

const emit = defineEmits([
    'close',
    'confirm',
])

const paymentMethod = ref('cash')

const paidAmount = ref('')

const note = ref('')

const payOldDebt = ref(false)

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

const balanceAmount = computed(() => {

    return (
        Number(paidAmount.value || 0)
        - totalNeedToPay.value
    )
})

const formatMoney = (value) => {

    return Number(
        value || 0
    ).toLocaleString('vi-VN')
}

const submitting = ref(false)



const submit = () => {

    if (submitting.value) {

        return
    }

    submitting.value = true

    emit(
        'confirm',
        {
            payment_method: paymentMethod.value,
            paid_amount: Number(
                paidAmount.value || 0
            ),
            note: note.value,
            pay_old_debt: payOldDebt.value,
        }
    )
}

watch(
    () => props.show,
    (value) => {

        if (!value) {

            submitting.value = false
        }
    }
)

</script>

<template>

<div
    v-if="show"
    class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center"
>

    <div
        class="bg-white w-[550px] rounded-xl p-6"
    >

        <div
            class="flex justify-between items-center mb-4"
        >

            <h2 class="font-bold text-xl">

                Thanh toán

            </h2>

            <button
                @click="emit('close')"
            >
                ✕
            </button>

        </div>

        <div class="space-y-4">

            <div>

                <label class="font-medium">

                    Phương thức thanh toán

                </label>

                <PaymentMethodSelect
                    v-model="paymentMethod"
                />

            </div>

            <div>

                <label class="font-medium">

                    Khách đưa

                </label>

                <input
                    v-model="paidAmount"
                    type="number"
                    class="w-full border rounded-lg p-3"
                />

            </div>

            <div
                v-if="
                    selectedCustomer &&
                    Number(selectedCustomer.debt_balance) > 0
                "
            >

                <label
                    class="flex items-center gap-2"
                >

                    <input
                        v-model="payOldDebt"
                        type="checkbox"
                    >

                    Thanh toán nợ cũ
                    (
                    {{
                        formatMoney(
                            selectedCustomer.debt_balance
                        )
                    }}
                    )

                </label>

            </div>

            <div>

                <label class="font-medium">

                    Ghi chú hóa đơn

                </label>

                <textarea
                    v-model="note"
                    rows="3"
                    class="w-full border rounded-lg p-3"
                />

            </div>

            <div
                class="bg-gray-100 p-4 rounded-lg"
            >

                <div class="flex justify-between">

                    <span>Tiền hàng</span>

                    <span>
                        {{
                            formatMoney(
                                grandTotal
                            )
                        }}
                    </span>

                </div>

                <div class="flex justify-between font-bold text-lg mt-2">

                    <span>Cần thu</span>

                    <span>
                        {{
                            formatMoney(
                                totalNeedToPay
                            )
                        }}
                    </span>

                </div>

                <div
                    class="flex justify-between mt-2"
                >

                    <span>Tiền thừa</span>

                    <span
                        class="text-green-600"
                    >

                        {{
                            formatMoney(
                                balanceAmount
                            )
                        }}

                    </span>

                </div>

            </div>

            <button
                @click="submit"
                class="w-full h-12 bg-green-600 text-white rounded-xl font-bold"
            >

                Xác nhận thanh toán

            </button>

        </div>

    </div>

</div>

</template>