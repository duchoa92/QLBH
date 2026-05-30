import {
    onMounted,
    onBeforeUnmount,
} from 'vue'

export function useBarcodeScanner(
    callback
) {

    let barcode = ''

    let timer = null

    const handleKeydown = async (
        event
    ) => {

        const target = event.target

        if (
            target instanceof HTMLInputElement ||
            target instanceof HTMLTextAreaElement
        ) {

            return
        }

        if (
            event.key === 'Enter'
        ) {

            if (
                barcode.length > 2
            ) {

                await callback(barcode)
            }

            barcode = ''

            return
        }

        if (
            event.key.length === 1
        ) {

            barcode += event.key
        }

        clearTimeout(timer)

        timer = setTimeout(
            () => {

                barcode = ''
            },
            100
        )
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