import { useToast }
from '@/Composables/useToast'

import { paymentValidator }
from '@/Modules/POS/Payment/Validators/paymentValidator'

export function usePaymentValidation() {

   

    const validatePayment =
        ({
            paidAmount,
            total,
        }) => {

        const result =
            paymentValidator({

                paidAmount,

                total,
            })

        if (!result.valid) {

            toast.error(
                result.message
            )

            return false
        }

        return true
    }

    return {

        validatePayment,
    }
}