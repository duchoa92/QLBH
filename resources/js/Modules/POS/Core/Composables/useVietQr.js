import { computed } from 'vue'

import { paymentConfig }
from '@/config/payment'
import { buildVietQrPayload, renderVietQrImage } from '@/Modules/POS/Payment/Utils/vietQr'

export function useVietQr(total) {

    const vietQrUrl = computed(() => {

        const { bankBin, bankAccount, description } = paymentConfig.vietQr
        const payload = buildVietQrPayload({
            bankBin,
            bankAccount,
            amount: Number(total.value || 0),
            description,
        })

        return renderVietQrImage(payload)
    })

    return {

        vietQrUrl,
    }
}
