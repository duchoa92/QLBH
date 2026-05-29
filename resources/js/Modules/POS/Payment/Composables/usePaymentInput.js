export function usePaymentInput(

    paidAmount,

    paidAmountDisplay,
) {

    const handlePaidInput =
        (value) => {

        paidAmountDisplay.value =
            value

        paidAmount.value =
            Number(
                String(value)
                    .replace(/\D/g, '')
            )
    }

    return {

        handlePaidInput,
    }
}