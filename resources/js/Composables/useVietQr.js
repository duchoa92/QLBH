import { computed } from 'vue'

import { paymentConfig }
from '@/config/payment'

export function useVietQr(total) {

    const vietQrUrl = computed(() => {

        const {

            bankBin,

            bankAccount,

            description,

        } = paymentConfig.vietQr

        return `https://img.vietqr.io/image/${bankBin}-${bankAccount}-compact2.png?amount=${Number(total.value || 0)}&addInfo=${description}`
    })

    return {

        vietQrUrl,
    }
}