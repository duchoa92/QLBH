import { ref } from 'vue'

export function usePaymentState() {

    const paidAmount =
        ref(0)

    const paidAmountDisplay =
        ref('')

    const paymentMethod =
        ref('cash')

    const loading =
        ref(false)

    return {

        paidAmount,

        paidAmountDisplay,

        paymentMethod,

        loading,
    }
}