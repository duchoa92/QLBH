import { formatCurrency } from '@/utils/format'

export const useMoney = () => {

    /*
    |--------------------------------------------------------------------------
    | Format tiền VNĐ
    |--------------------------------------------------------------------------
    */

    const formatMoney = formatCurrency

    /*
    |--------------------------------------------------------------------------
    | Parse tiền
    |--------------------------------------------------------------------------
    */

    const parseMoney = (value) => {

        return Number(
            String(value || 0)
                .replace(/\D/g, '')
        )
    }

    return {

        formatMoney,

        parseMoney,
    }
}
