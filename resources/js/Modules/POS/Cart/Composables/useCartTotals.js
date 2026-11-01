import { computed } from 'vue'

export const useCartTotals = (
    cart,
    invoiceDiscount = { value: 0 },
    extraFee = { value: 0 },
    invoiceDiscountType = { value: 'amount' },
) => {

    /*
    |--------------------------------------------------------------------------
    | Total Quantity
    |--------------------------------------------------------------------------
    */

    const totalQuantity =
        computed(() => {

            return cart.value.reduce(
                (total, item) => {

                    return total +
                        Number(
                            item.quantity
                        )
                },
                0
            )
        })

    /*
    |--------------------------------------------------------------------------
    | Sub Total
    |--------------------------------------------------------------------------
    */

    const subTotal = computed(() => {

        return cart.value.reduce(

            (total, item) => {

                return total +

                    (
                        Number(item.price)
                        *
                        Number(item.quantity)
                    )

            },

            0
        )
    })

    /*
    |--------------------------------------------------------------------------
    | Discount
    |--------------------------------------------------------------------------
    */

    const discount = computed(() => {

        return cart.value.reduce(

            (total, item) => {

                const lineTotal =

                    Number(item.price)
                    *
                    Number(item.quantity)

                /*
                |--------------------------------------------------
                | Giảm %
                |--------------------------------------------------
                */

                if (
                    item.discount_type === 'percent'
                ) {

                    return (
                        total +
                        (
                            lineTotal *
                            Number(item.discount_value)
                            / 100
                        )
                    )
                }

                /*
                |--------------------------------------------------
                | Giảm tiền
                |--------------------------------------------------
                */

                if (
                    item.discount_type === 'amount'
                ) {

                    return (
                        total +
                        Number(item.discount_value)
                    )
                }

                return total

            },

            0
        )

    })

    /*
    |--------------------------------------------------------------------------
    | VAT
    |--------------------------------------------------------------------------
    */

    const vat = computed(() => {
        return 0
    })

    const invoiceDiscountAmount = computed(() => {
        const base = Math.max(0, subTotal.value - discount.value)
        const value = Math.max(0, Number(invoiceDiscount.value || 0))
        return invoiceDiscountType.value === 'percent'
            ? base * Math.min(100, value) / 100
            : Math.min(base, value)
    })

    /*
    |--------------------------------------------------------------------------
    | Grand Total
    |--------------------------------------------------------------------------
    */

    const grandTotal =
        computed(() => {

            return (
                subTotal.value
                - discount.value
                + vat.value
                - invoiceDiscountAmount.value
                + Math.max(0, Number(extraFee.value || 0))
            )
        })

    return {

        totalQuantity,

        subTotal,

        discount,

        vat,

        invoiceDiscount,

        invoiceDiscountAmount,

        extraFee,

        grandTotal,
    }
}
