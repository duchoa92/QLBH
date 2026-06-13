import {
    onMounted,
    onBeforeUnmount,
} from 'vue'

export function useKeyboardShortcuts({

    cart,

    selectedCartIndex,

    checkout,

    showPaymentModal,

    clearCart,
}) {

    const handleKeydown = (
        event
    ) => {

        /*
        |--------------------------------------------------------------------------
        | Ignore typing
        |--------------------------------------------------------------------------
        */

        const tagName =
            event.target.tagName

        const isTyping =

            tagName === 'INPUT'

            || tagName === 'TEXTAREA'

            || event.target.isContentEditable

        if (isTyping) {

            return
        }

        /*
        |--------------------------------------------------------------------------
        | ESC
        |--------------------------------------------------------------------------
        */

        if (event.key === 'Escape') {

            showPaymentModal.value =
                false
        }

        /*
        |--------------------------------------------------------------------------
        | F2
        |--------------------------------------------------------------------------
        */

        if (event.key === 'F2') {

            event.preventDefault()

            checkout()
        }

        /*
        |--------------------------------------------------------------------------
        | DELETE
        |--------------------------------------------------------------------------
        */

        if (

            event.key === 'Delete'

            &&

            !event.ctrlKey
        ) {

            const item =

                cart.value[
                    selectedCartIndex.value
                ]

            if (!item) {

                return
            }

            cart.value.splice(

                selectedCartIndex.value,

                1
            )

            if (

                selectedCartIndex.value >

                cart.value.length - 1
            ) {

                selectedCartIndex.value =
                    Math.max(

                        0,

                        cart.value.length - 1
                    )
            }
        }

        /*
        |--------------------------------------------------------------------------
        | CTRL + DELETE
        |--------------------------------------------------------------------------
        */

        if (

            event.key === 'Delete'

            &&

            event.ctrlKey
        ) {

            event.preventDefault()

            const confirmed = confirm(
                'Xóa toàn bộ giỏ hàng?'
            )

            if (confirmed) {

                clearCart()
            }
        }

        /*
        |--------------------------------------------------------------------------
        | +
        |--------------------------------------------------------------------------
        */

        if (

            event.key === '+'

            ||

            event.key === '='
        ) {

            const item =

                cart.value[
                    selectedCartIndex.value
                ]

            if (item) {

                item.quantity++
            }
        }

        /*
        |--------------------------------------------------------------------------
        | -
        |--------------------------------------------------------------------------
        */

        if (

            event.key === '-'

            ||

            event.key === '_'
        ) {

            const item =

                cart.value[
                    selectedCartIndex.value
                ]

            if (

                item

                &&

                item.quantity > 1
            ) {

                item.quantity--
            }
        }

        /*
        |--------------------------------------------------------------------------
        | ARROW DOWN
        |--------------------------------------------------------------------------
        */

        if (event.key === 'ArrowDown') {

            if (

                selectedCartIndex.value <

                cart.value.length - 1
            ) {

                selectedCartIndex.value++
            }
        }

        /*
        |--------------------------------------------------------------------------
        | ARROW UP
        |--------------------------------------------------------------------------
        */

        if (event.key === 'ArrowUp') {

            if (

                selectedCartIndex.value > 0
            ) {

                selectedCartIndex.value--
            }
        }
    }

    onMounted(() => {

        window.addEventListener(
            'keydown',
            handleKeydown
        )
    })

    onBeforeUnmount(() => {

        window.removeEventListener(
            'keydown',
            handleKeydown
        )
    })
}