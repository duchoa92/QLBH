export function paymentValidator({

    paidAmount,

    total,
}) {

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