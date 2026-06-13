import { computed } from 'vue'

import { useVietQr }
from '@/Modules/POS/Core/Composables/useVietQr'

export function usePaymentQr(

    props,
) {

    const {

        vietQrUrl,

    } = useVietQr(

        computed(() => props.total)
    )

    return {

        vietQrUrl,
    }
}