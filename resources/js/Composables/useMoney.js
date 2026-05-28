export const useMoney = () => {

    /*
    |--------------------------------------------------------------------------
    | Format tiền VNĐ
    |--------------------------------------------------------------------------
    */

    const formatMoney = (value) => {

        return Number(value || 0)
            .toLocaleString('vi-VN')
    }

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