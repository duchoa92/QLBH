import {
    onMounted,
    onBeforeUnmount,
} from 'vue'

export function usePaymentKeyboard(

    props,

    emit,

    confirmPayment,
) {

    const handleKeydown =
        (event) => {

        if (!props.show) {

            return
        }

        // ESC
        if (
            event.key === 'Escape'
        ) {

            emit('close')
        }

        // ENTER
        if (
            event.key === 'Enter'
        ) {

            confirmPayment()
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