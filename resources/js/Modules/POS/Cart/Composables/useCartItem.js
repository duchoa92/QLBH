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

    const toggleWarranty = (item) => {

        item.showWarranty = !item.showWarranty
    }

    return {

        toggleNote,

        togglePromotion,

        toggleWarranty,
    }
}
