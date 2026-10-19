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

                    item.gift_results = results.slice(0, 10)

                } catch (error) {

                    console.error(error)

                    item.gift_results = []
                }

            },
            300
        )
    }

    const selectGiftProduct = async (
        item,
        product
    ) => {

        if (!item.gifts) {

            item.gifts = []
        }

        const requiresImei =
            product.product_type === 'imei'
            || Boolean(product.manage_stock_by_serial)

        if (requiresImei) {
            try {
                const imeiOptions = await productService.imeis(product.id)

                if (!imeiOptions.length) {
                    return
                }

                item.gifts.push({
                    key: `gift-imei-${product.id}-${Date.now()}-${Math.random()}`,
                    id: product.id,
                    name: product.name,
                    sku: product.sku,
                    quantity: 1,
                    requires_imei: true,
                    imei_id: imeiOptions.length === 1 ? imeiOptions[0].id : null,
                    imei_options: imeiOptions,
                })
            } catch (error) {
                console.error(error)
            }

            item.gift_keyword = ''
            item.gift_results = []

            return
        }

        const gift =
            item.gifts.find(
                g => g.id === product.id && !g.requires_imei
            )

        if (gift) {

            gift.quantity++
        }
        else {

            item.gifts.push({
                key: `gift-${product.id}`,
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
                g => g.key === giftId
                    || (!g.key && g.id === giftId)
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
                    g => g.key !== giftId
                        && (g.key || g.id) !== giftId
                )
        }
    }

    return {

        searchGiftProducts,

        selectGiftProduct,

        removeGift,
    }
}
