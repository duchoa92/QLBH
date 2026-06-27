import { productService } from '@/Modules/POS/Product/Services/productService'

let giftTimer = null

export function useCartItem() {

    const toggleNote = (item) => {

        item.showNote =
            !item.showNote
    }

    const togglePromotion = (
        item
    ) => {

        item.showPromotion =
            !item.showPromotion
    }

    return {

        toggleNote,

        togglePromotion,
    }
}