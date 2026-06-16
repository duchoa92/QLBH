export default {

    mounted(el, binding) {

        let mouseDownInside = false

        el.__mouseDown__ = (event) => {

            mouseDownInside =
                el.contains(event.target)
        }

        el.__clickOutside__ = (event) => {

            const clickInside =

                el.contains(
                    event.target
                )

            if (
                !mouseDownInside &&
                !clickInside
            ) {

                binding.value(event)
            }

            mouseDownInside = false
        }

        document.addEventListener(
            'mousedown',
            el.__mouseDown__
        )

        document.addEventListener(
            'mouseup',
            el.__clickOutside__
        )
    },

    unmounted(el) {

        document.removeEventListener(
            'mousedown',
            el.__mouseDown__
        )

        document.removeEventListener(
            'mouseup',
            el.__clickOutside__
        )
    },
}