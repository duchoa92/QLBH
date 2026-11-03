import { computed } from 'vue'

export const useCartTotals = (
    cart,
    invoiceDiscount = { value: 0 },
    extraFee = { value: 0 },
    invoiceDiscountType = { value: 'amount' },
    taxRate = { value: 0 },
    extraFeeType = { value: 'amount' },
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
        const lineBase = Math.max(0, subTotal.value - discount.value)
        if (!lineBase) return 0
        const taxableBase = cart.value.reduce((total, item) => {
            if (!item.apply_vat) return total
            const lineTotal = Number(item.price || 0) * Number(item.quantity || 0)
            const lineDiscount = item.discount_type === 'percent'
                ? lineTotal * Math.min(100, Number(item.discount_value || 0)) / 100
                : item.discount_type === 'amount'
                    ? Math.min(lineTotal, Number(item.discount_value || 0))
                    : 0
            return total + Math.max(0, lineTotal - lineDiscount)
        }, 0)
        const afterInvoiceDiscount = Math.max(0, taxableBase - invoiceDiscountAmount.value * taxableBase / lineBase)
        return Math.round(afterInvoiceDiscount * Math.max(0, Number(taxRate.value || 0)) / 100)
    })

    const invoiceDiscountAmount = computed(() => {
        const base = Math.max(0, subTotal.value - discount.value)
        const value = Math.max(0, Number(invoiceDiscount.value || 0))
        return invoiceDiscountType.value === 'percent'
            ? base * Math.min(100, value) / 100
            : Math.min(base, value)
    })

    const extraFeeAmount = computed(() => {
        const value = Math.max(0, Number(extraFee.value || 0))
        if (extraFeeType.value !== 'percent') return value
        const base = Math.max(0, subTotal.value - discount.value - invoiceDiscountAmount.value)
        return Math.round(base * value / 100)
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
                + extraFeeAmount.value
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
        extraFeeAmount,

        grandTotal,
    }
}
