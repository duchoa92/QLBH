export function usePaymentSubmit(

    props,

    emit,

    loading,

    paidAmount,

    paymentMethod,

    validatePayment,
) {

    const confirmPayment =
        async () => {

        const isValid =
            validatePayment({

                paidAmount:
                    paidAmount.value,

                total:
                    props.total,
            })

        if (!isValid) {

            return
        }

        loading.value = true

        try {

            await emit(
                'confirm',
                {

                    paid_amount:
                        paidAmount.value,

                    payment_method:
                        paymentMethod.value,
                }
            )

        } finally {

            loading.value = false
        }
    }

    return {

        confirmPayment,
    }
}