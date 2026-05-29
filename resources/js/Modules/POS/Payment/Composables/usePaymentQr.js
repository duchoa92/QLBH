import { computed } from 'vue'

import { useVietQr }
from '@/Composables/useVietQr'

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