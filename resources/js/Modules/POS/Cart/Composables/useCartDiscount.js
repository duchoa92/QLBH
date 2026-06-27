export function useCartDiscount() {

    const lineTotal = (item) => {

        const total =

            Number(item.price)
            *
            Number(item.quantity)

        if (
            item.discount_type === 'percent'
        ) {

            return total -
                (
                    total *
                    Number(item.discount_value)
                    / 100
                )
        }

        if (
            item.discount_type === 'amount'
        ) {

            return total -
                Number(item.discount_value)
        }

        return total
    }

    const normalizeDiscount = (
        item
    ) => {

        if (!item.discount_value) {

            item.discount_value = 0

            return
        }

        if (
            item.discount_type === 'percent'
        ) {

            item.discount_value =
                Math.min(
                    100,
                    Math.max(
                        0,
                        item.discount_value
                    )
                )

            return
        }

        const maxDiscount =

            Number(item.price)
            *
            Number(item.quantity)

        item.discount_value =
            Math.min(
                maxDiscount,
                Math.max(
                    0,
                    item.discount_value
                )
            )
    }

    return {

        lineTotal,

        normalizeDiscount,
    }
}