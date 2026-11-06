<script setup>
import { nextTick, ref } from 'vue'

const props = defineProps({
    text: String,
    position: { type: String, default: 'top' }, // top | bottom | left | right
    delay: { type: Number, default: 50 }
})

const show = ref(false)
const tooltipStyle = ref({})
let timeout = null

const onEnter = (event) => {
    clearTimeout(timeout)
    const target = event.currentTarget
    timeout = setTimeout(() => {
        show.value = true
        nextTick(() => {
            const anchor = target.getBoundingClientRect()
            const tooltip = target.querySelector('[data-tooltip]')
            if (!tooltip) return

            const margin = 8
            const gap = 8
            const width = tooltip.offsetWidth
            const height = tooltip.offsetHeight
            const left = Math.min(
                Math.max(anchor.left + anchor.width / 2 - width / 2, margin),
                window.innerWidth - width - margin
            )
            const preferredTop = props.position === 'bottom'
                ? anchor.bottom + gap
                : anchor.top - height - gap
            const top = props.position === 'bottom'
                ? Math.min(preferredTop, window.innerHeight - height - margin)
                : (preferredTop >= margin ? preferredTop : anchor.bottom + gap)

            tooltipStyle.value = { left: `${left}px`, top: `${top}px` }
        })
    }, props.delay)
}

const onLeave = () => {
    clearTimeout(timeout)
    show.value = false
}
</script>

<template>
<div
    class="inline-flex"
    @mouseenter="onEnter"
    @mouseleave="onLeave"
>
    <!-- SLOT -->
    <slot />

    <!-- TOOLTIP -->
    <transition name="fade">
        <div
            v-if="show"
            data-tooltip
            class="pointer-events-none fixed z-[9999] max-w-[calc(100vw-16px)] px-3 py-1.5 text-xs text-white bg-gray-900 rounded-md whitespace-nowrap"
            :style="tooltipStyle"
        >
            {{ text }}

            <!-- ARROW -->
            <div :class="arrowClass"></div>
        </div>
    </transition>
</div>
</template>

<script>
export default {
    computed: {
        positionClass() {
            switch (this.position) {
                case 'bottom':
                    return 'top-full mt-2 left-1/2 -translate-x-1/2'
                case 'left':
                    return 'right-full mr-2 top-1/2 -translate-y-1/2'
                case 'right':
                    return 'left-full ml-2 top-1/2 -translate-y-1/2'
                default:
                    return 'bottom-full mb-2 left-1/2 -translate-x-1/2'
            }
        },
        arrowClass() {
            switch (this.position) {
                case 'bottom':
                    return 'absolute bottom-full left-1/2 -translate-x-1/2 border-4 border-transparent border-b-gray-900'
                case 'left':
                    return 'absolute left-full top-1/2 -translate-y-1/2 border-4 border-transparent border-l-gray-900'
                case 'right':
                    return 'absolute right-full top-1/2 -translate-y-1/2 border-4 border-transparent border-r-gray-900'
                default:
                    return 'absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-gray-900'
            }
        }
    }
}
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.15s ease, transform 0.15s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
    transform: scale(0.95);
}
</style>
