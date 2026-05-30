export function cartTotal(items) {

    return items.reduce(
        (total, item) => {

            return total +
                (
                    Number(item.price) *
                    Number(item.quantity)
                )
        },
        0
    )
}