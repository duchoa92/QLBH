import {

    ref,

    computed,

    watch,

    nextTick,

    onMounted,

    onBeforeUnmount,

} from 'vue'

import { useMoney }
from '@/Composables/useMoney'

import { useToast }
from '@/Composables/useToast'

export function usePayment(

    props,

    emit,

    paidInputRef,
) {

    const toast = useToast()

    const {

        formatMoney,

    } = useMoney()

    /*
    |--------------------------------------------------------------------------
    | STATE
    |--------------------------------------------------------------------------
    */

    const paidAmount = ref(0)

    const paidAmountDisplay =
        ref('')

    const paymentMethod =
        ref('cash')

    /*
    |--------------------------------------------------------------------------
    | QR
    |--------------------------------------------------------------------------
    */

    const vietQrUrl = computed(() => {

        const amount =
            Number(props.total || 0)

        const description =
            'POS_PAYMENT'

        const bankBin =
            '970422'

        const bankAccount =
            '0123456789'

        return `https://img.vietqr.io/image/${bankBin}-${bankAccount}-compact2.png?amount=${amount}&addInfo=${description}`
    })

    /*
    |--------------------------------------------------------------------------
    | CHANGE
    |--------------------------------------------------------------------------
    */

    const changeAmount =
        computed(() => {

        return Number(
            paidAmount.value
        ) - Number(props.total)
    })

    /*
    |--------------------------------------------------------------------------
    | INPUT
    |--------------------------------------------------------------------------
    */

    const handlePaidInput =
        (event) => {

        const rawValue =
            event.target.value
                .replace(/\D/g, '')

        paidAmount.value =
            Number(rawValue)

        paidAmountDisplay.value =
            formatMoney(rawValue)
    }

    /*
    |--------------------------------------------------------------------------
    | CONFIRM
    |--------------------------------------------------------------------------
    */

    const confirmPayment = () => {

        if (
            paidAmount.value <
            props.total
        ) {

            toast.error(
                'Khách đưa chưa đủ tiền'
            )

            return
        }

        emit('confirm', {

            paid_amount:
                paidAmount.value,

            payment_method:
                paymentMethod.value,
        })
    }

    /*
    |--------------------------------------------------------------------------
    | RESET
    |--------------------------------------------------------------------------
    */

    watch(
        () => props.show,
        (value) => {

            if (!value) {

                return
            }

            paidAmount.value =
                props.total

            paidAmountDisplay.value =
                formatMoney(
                    props.total
                )

            nextTick(() => {

                paidInputRef.value
                    ?.focus()

                paidInputRef.value
                    ?.select()
            })
        }
    )

    /*
    |--------------------------------------------------------------------------
    | KEYBOARD
    |--------------------------------------------------------------------------
    */

    const handleKeydown =
        (event) => {

        if (!props.show) {

            return
        }

        if (
            event.key === 'Escape'
        ) {

            emit('close')
        }

        if (
            event.key === 'Enter'
        ) {

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

    return {

        paidAmount,

        paidAmountDisplay,

        paymentMethod,

        vietQrUrl,

        changeAmount,

        formatMoney,

        handlePaidInput,

        confirmPayment,
    }
}