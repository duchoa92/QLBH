import { computed } from 'vue'

export const useCartTotals = (
    cart
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
                        Number(item.price) *
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

        return 0
    })

    /*
    |--------------------------------------------------------------------------
    | VAT
    |--------------------------------------------------------------------------
    */

    const vat = computed(() => {

        return 0
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
            )
        })

    return {

        totalQuantity,

        subTotal,

        discount,

        vat,

        grandTotal,
    }
}