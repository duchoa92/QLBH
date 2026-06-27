import { productService }
from '@/Modules/POS/Product/Services/productService'

let giftTimer = null

export function useCartGift() {

    const searchGiftProducts = async (
        item
    ) => {

        clearTimeout(
            giftTimer
        )

        giftTimer = setTimeout(
            async () => {

                if (
                    !item.gift_keyword ||
                    item.gift_keyword.length < 2
                ) {

                    item.gift_results = []

                    return
                }

                try {

                    const results =
                        await productService.search(
                            item.gift_keyword
                        )

                    item.gift_results =
                        results
                            .filter(
                                product =>
                                    product.product_type !== 'imei'
                            )
                            .slice(0, 10)

                } catch (error) {

                    console.error(error)

                    item.gift_results = []
                }

            },
            300
        )
    }

    const selectGiftProduct = (
        item,
        product
    ) => {

        if (!item.gifts) {

            item.gifts = []
        }

        const gift =
            item.gifts.find(
                g => g.id === product.id
            )

        if (gift) {

            gift.quantity++
        }
        else {

            item.gifts.push({

                id: product.id,

                name: product.name,

                sku: product.sku,

                quantity: 1,
            })
        }

        item.gift_keyword = ''

        item.gift_results = []
    }

    const removeGift = (
        item,
        giftId
    ) => {

        const gift =
            item.gifts.find(
                g => g.id === giftId
            )

        if (!gift) {

            return
        }

        gift.quantity--

        if (
            gift.quantity <= 0
        ) {

            item.gifts =
                item.gifts.filter(
                    g => g.id !== giftId
                )
        }
    }

    return {

        searchGiftProducts,

        selectGiftProduct,

        removeGift,
    }
}