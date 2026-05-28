export const usePaymentValidation = () => {

    /*
    |--------------------------------------------------------------------------
    | Validate Payment
    |--------------------------------------------------------------------------
    */

    const validatePayment = (

        paidAmount,

        total,
    ) => {

        /*
        |--------------------------------------------------------------------------
        | Chưa đủ tiền
        |--------------------------------------------------------------------------
        */

        if (
            Number(paidAmount) <
            Number(total)
        ) {

            return {

                valid: false,

                message:
                    'Khách đưa chưa đủ tiền',
            }
        }

        return {

            valid: true,

            message: null,
        }
    }

    return {

        validatePayment,
    }
}