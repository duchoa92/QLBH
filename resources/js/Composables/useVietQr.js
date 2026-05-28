import { computed } from 'vue'

export const useVietQr = (
    total
) => {

    /*
    |--------------------------------------------------------------------------
    | Config
    |--------------------------------------------------------------------------
    */

    const bankBin = '970422'

    const bankAccount =
        '0123456789'

    const description =
        'POS_PAYMENT'

    /*
    |--------------------------------------------------------------------------
    | QR URL
    |--------------------------------------------------------------------------
    */

    const vietQrUrl = computed(() => {

        const amount =
            Number(total.value || 0)

        return `https://img.vietqr.io/image/${bankBin}-${bankAccount}-compact2.png?amount=${amount}&addInfo=${description}`
    })

    return {

        vietQrUrl,
    }
}