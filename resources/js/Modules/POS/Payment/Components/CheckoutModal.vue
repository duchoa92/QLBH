<script setup>

import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import PaymentMethodSelect from './PaymentMethodSelect.vue'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import QrPayment from './QrPayment.vue'

const props = defineProps({
    loading: Boolean,
    show: Boolean,
    grandTotal: Number,
    selectedCustomer: Object,

    cart: {
        type: Array,
        default: () => [],
    },
})

const emit = defineEmits([
    'close',
    'confirm',
])

const paymentMethod = ref('cash')

const paidAmount = ref('')

const note = ref('')

const payOldDebt = ref(false)

const page = usePage()

const bankSettings = computed(() =>
    page.props.settings || {}
)

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

const transferAmount = computed(() =>
    Math.max(0, Number(totalNeedToPay.value || 0))
)

const effectivePaidAmount = computed(() =>
    paymentMethod.value === 'bank'
        ? Number(paidAmount.value || transferAmount.value || 0)
        : Number(paidAmount.value || 0)
)

const balanceAmount = computed(() => {

    return (
        effectivePaidAmount.value
        - totalNeedToPay.value
    )
})

const formatMoney = (value) => {

    return Number(
        value || 0
    ).toLocaleString('vi-VN')
}

const formatMoneyInput = (value) => {
    const number = Number(value || 0)

    return number > 0
        ? number.toLocaleString('vi-VN')
        : ''
}

const parseMoneyInput = (value) =>
    Number(String(value || '').replace(/\D/g, ''))

const handlePaidAmountInput = (event) => {
    paidAmount.value = parseMoneyInput(event.target.value)
}

const balanceStatus = computed(() => {
    if (balanceAmount.value < 0) {
        return {
            label: 'Thiếu',
            amount: Math.abs(balanceAmount.value),
            className: 'text-red-600',
        }
    }

    if (balanceAmount.value > 0) {
        return {
            label: 'Thừa',
            amount: balanceAmount.value,
            className: 'text-green-600',
        }
    }

    return {
        label: 'Đủ',
        amount: 0,
        className: 'text-slate-700',
    }
})

const hasBankSettings = computed(() =>
    Boolean(bankSettings.value.bank_bin && bankSettings.value.bank_account)
)

const vietQrUrl = computed(() => {
    if (!hasBankSettings.value) {
        return ''
    }

    const description = encodeURIComponent(
        bankSettings.value.bank_transfer_content
        || 'Thanh toan don hang'
    )

    return `https://img.vietqr.io/image/${bankSettings.value.bank_bin}-${bankSettings.value.bank_account}-compact2.png?amount=${transferAmount.value}&addInfo=${description}`
})



const submit = () => {
    emit(
        'confirm',
        {
            payment_method: paymentMethod.value,
            paid_amount: effectivePaidAmount.value,
            note: note.value,
            pay_old_debt: payOldDebt.value,
        }
    )
}

const lineTotal = (item) => {

    let total =

        Number(item.price)
        *
        Number(item.quantity)

    if (
        item.discount_type === 'amount'
    ) {

        total -=
            Number(item.discount_value)
    }

    if (
        item.discount_type === 'percent'
    ) {

        total -=
            (
                total *
                Number(item.discount_value)
                / 100
            )
    }

    return total
}


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
                <FloatingInput
                    :model-value="formatMoneyInput(paidAmount)"
                    @input="handlePaidAmountInput"
                    type="text"
                    inputmode="numeric"
                    :label="paymentMethod === 'bank' ? 'Số tiền đã chuyển' : 'Khách đưa'"
                />

            </div>

            <div v-if="paymentMethod === 'bank'">
                <QrPayment
                    v-if="vietQrUrl"
                    :url="vietQrUrl"
                    :amount="transferAmount"
                    :account="bankSettings.bank_account"
                    :account-name="bankSettings.bank_account_name"
                />

                <div
                    v-else
                    class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm font-semibold text-amber-700"
                >
                    Chưa thiết lập tài khoản ngân hàng trong trang Cài đặt.
                </div>
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
                <FloatingInput
                    v-model="note"
                    label="Ghi chú hóa đơn"

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

                    <span>{{ balanceStatus.label }}</span>

                    <span
                        :class="balanceStatus.className"
                        class="font-bold"
                    >

                        {{
                            formatMoney(
                                balanceStatus.amount
                            )
                        }}

                    </span>

                </div>

            </div>

            <button
                :disabled="loading"
                @click="submit"
                class="w-full h-12 bg-green-600 text-white rounded-xl font-bold"
            >
                {{ loading ? 'Đang xử lý...' : 'Xác nhận thanh toán' }}
            </button>

        </div>

    </div>

</div>

</template>
