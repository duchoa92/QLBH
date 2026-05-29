import { usePaymentValidation } from '@/Modules/POS/Payment/Composables/usePaymentValidation'
import { usePaymentState } from '@/Modules/POS/Payment/Composables/usePaymentState'
import { usePaymentReset } from '@/Modules/POS/Payment/Composables/usePaymentReset'
import { usePaymentInput } from '@/Modules/POS/Payment/Composables/usePaymentInput'
import { usePaymentQr } from '@/Modules/POS/Payment/Composables/usePaymentQr'
import { usePaymentSubmit } from '@/Modules/POS/Payment/Composables/usePaymentSubmit'
import { usePaymentSummary } from '@/Modules/POS/Payment/Composables/usePaymentSummary'

export function usePayment(

    props,

    emit,

    paidInputRef,
) {



    const {

        validatePayment,

    } = usePaymentValidation()



    /*
    |--------------------------------------------------------------------------
    | STATE
    |--------------------------------------------------------------------------
    */

    const {

        paidAmount,

        paidAmountDisplay,

        paymentMethod,

        loading,

    } = usePaymentState()

   
    
    // Payment input
    const {

        handlePaidInput,

    } = usePaymentInput(

        paidAmount,

        paidAmountDisplay,
    )

    // Payment QR
    const {

        vietQrUrl,

    } = usePaymentQr(

        props,
    )

    // Payment summary
    const {

        changeAmount,

        formatMoney,

    } = usePaymentSummary(

        props,

        paidAmount,
    )

    // Submit payment
    const {

        confirmPayment,

    } = usePaymentSubmit(

        props,

        emit,

        loading,

        paidAmount,

        paymentMethod,

        validatePayment,
    )

    // Reset payment
    usePaymentReset(

        props,

        paidAmount,

        paidAmountDisplay,

        formatMoney,

        paidInputRef,
    )
    
    

    return {

        loading,

        paidAmount,

        paidAmountDisplay,

        paymentMethod,

        vietQrUrl,

        changeAmount,

        formatMoney,

        handlePaidInput,

        confirmPayment,
        
    }
}