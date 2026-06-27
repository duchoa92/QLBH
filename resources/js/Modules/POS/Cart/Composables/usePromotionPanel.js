import {
    onMounted,
    onBeforeUnmount,
} from 'vue'

export function usePromotionPanel(
    items
) {

    const handleClickOutside = (
        event
    ) => {

        const insidePromotion =

            event.target.closest(
                '.promotion-panel'
            )

        const clickButton =

            event.target.closest(
                '.promotion-button'
            )

        if (
            insidePromotion ||
            clickButton
        ) {

            return
        }

        items.value.forEach(
            item => {

                item.showPromotion =
                    false
            }
        )
    }

    onMounted(() => {

        document.addEventListener(
            'mousedown',
            handleClickOutside
        )
    })

    onBeforeUnmount(() => {

        document.removeEventListener(
            'mousedown',
            handleClickOutside
        )
    })
}