import { computed } from 'vue'

import { useMoney }
from '@/Modules/POS/Core/Utils/useMoney'

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