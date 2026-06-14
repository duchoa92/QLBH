<script setup>
import { ref, computed, watch } from 'vue'
import PaymentMethodSelect from './PaymentMethodSelect.vue'

const props = defineProps({

    show: Boolean,

    grandTotal: Number,

    customer: Object,
})

const emit = defineEmits([
    'close',
    'confirm',
])

const paymentMethod = ref('cash')

const paidAmount = ref('')

const note = ref('')

const payOldDebt = ref(false)

watch(
    () => props.show,
    (value) => {

        if (!value) {
            return
        }

        paymentMethod.value = 'cash'
        paidAmount.value = ''
        note.value = ''
        payOldDebt.value = false
    }
)

const totalNeedToPay = computed(() => {

    const debt = payOldDebt.value
        ? Number(
            props.customer?.debt_balance || 0
        )
        : 0

    return Number(
        props.grandTotal
    ) + debt
})

const balanceAmount = computed(() => {

    return (
        Number(paidAmount.value || 0)
        - totalNeedToPay.value
    )
})

const submit = () => {

    emit(
        'confirm',
        {

            payment_method:
                paymentMethod.value,

            paid_amount:
                Number(
                    paidAmount.value || 0
                ),

            note:
                note.value,

            pay_old_debt:
                payOldDebt.value,
        }
    )
}
</script>

<template>

<div
    v-if="show"
    class="fixed inset-0 bg-black/50 z-[9999] flex items-center justify-center"
>

    <div
        class="bg-white w-[500px] rounded-xl p-5"
    >

        <div class="flex justify-between mb-4">

            <h2 class="font-bold text-lg">
                Thanh toán
            </h2>

            <button
                @click="$emit('close')"
            >
                ✕
            </button>

        </div>

        <div class="space-y-4">

            <div>

                <label class="text-sm">
                    Phương thức thanh toán
                </label>

                <PaymentMethodSelect
                    v-model="paymentMethod"
                />

            </div>

            <div>

                <label class="text-sm">
                    Tiền khách đưa
                </label>

                <input
                    v-model="paidAmount"
                    type="number"
                    class="w-full border rounded-lg p-3"
                >

            </div>

            <div>

                <label class="text-sm">
                    Ghi chú hóa đơn
                </label>

                <textarea
                    v-model="note"
                    rows="3"
                    class="w-full border rounded-lg p-3"
                />

            </div>

            <div
                v-if="
                    customer &&
                    Number(customer.debt_balance) > 0
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

                    {{
                        Number(
                            customer.debt_balance
                        ).toLocaleString('vi-VN')
                    }}

                </label>

            </div>

            <div class="border-t pt-3">

                <div class="flex justify-between">

                    <span>Cần thu</span>

                    <span class="font-bold">

                        {{
                            totalNeedToPay.toLocaleString('vi-VN')
                        }}

                    </span>

                </div>

                <div
                    v-if="balanceAmount > 0"
                    class="text-green-600 mt-2"
                >

                    Tiền thừa:

                    {{
                        balanceAmount.toLocaleString('vi-VN')
                    }}

                </div>

            </div>

            <button
                @click="submit"
                class="w-full bg-green-600 text-white rounded-lg h-12 font-bold"
            >

                Xác nhận thanh toán

            </button>

        </div>

    </div>

</div>

</template>