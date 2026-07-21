<script setup>
import { ref } from 'vue'

const props = defineProps({
    text: String,
    position: { type: String, default: 'top' }, // top | bottom | left | right
    delay: { type: Number, default: 50 }
})

const show = ref(false)
let timeout = null

const onEnter = () => {
    timeout = setTimeout(() => {
        show.value = true
    }, props.delay)
}

const onLeave = () => {
    clearTimeout(timeout)
    show.value = false
}
</script>

<template>
<div
    class="relative inline-flex"
    @mouseenter="onEnter"
    @mouseleave="onLeave"
>
    <!-- SLOT -->
    <slot />

    <!-- TOOLTIP -->
    <transition name="fade">
        <div
            v-if="show"
            class="absolute z-50 px-3 py-1.5 text-xs text-white bg-gray-900 rounded-md whitespace-nowrap"
            :class="positionClass"
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