import { computed } from 'vue'

import { useMoney }
from '@/Composables/useMoney'

export function usePaymentSummary(

    props,

    paidAmount,
) {

    const {

        formatMoney,

    } = useMoney()

    const changeAmount =
        computed(() => {

            return Number(
                paidAmount.value
            ) - Number(props.total)
        })

    return {

        changeAmount,

        formatMoney,
    }
}