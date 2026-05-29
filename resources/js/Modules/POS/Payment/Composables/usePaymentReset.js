import {
    watch,
    nextTick,
} from 'vue'

export function usePaymentReset(

    props,

    paidAmount,

    paidAmountDisplay,

    formatMoney,

    paidInputRef,
) {

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
}