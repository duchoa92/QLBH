<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    maxWidth: {
        type: String,
        default: '2xl',
    },
    closeable: {
        type: Boolean,
        default: true,
    },
     title: String
});

const emit = defineEmits(['close']);



watch(
    () => props.show,
    (val) => {
        document.body.style.overflow = val ? 'hidden' : ''
    }
);

const close = () => {
    if (props.closeable) {
        emit('close');
    }
};

const closeOnEscape = (e) => {
    if (e.key === 'Escape') {
        e.preventDefault();

        if (props.show) {
            close();
        }
    }
};

onMounted(() => {
    document.addEventListener('keydown', closeOnEscape)

    if (props.show) {
        setTimeout(() => {
            document.querySelector('input, select, textarea')?.focus()
        }, 100)
    }
})

onUnmounted(() => {
    document.removeEventListener('keydown', closeOnEscape);

    document.body.style.overflow = '';
});

const maxWidthClass = computed(() => {
    return {
        sm: 'sm:max-w-sm',
        md: 'sm:max-w-md',
        lg: 'sm:max-w-lg',
        xl: 'sm:max-w-xl',
        '2xl': 'sm:max-w-2xl',
    }[props.maxWidth];
});
</script>

<template>
<div v-show="show" class="fixed inset-0 z-[1000] flex items-center justify-center">

    <!-- overlay -->
    <Transition
        enter-active-class="ease-out duration-300"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="ease-in duration-200"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div
            v-if="show"
            class="absolute inset-0 bg-black/50"
            @click="close"
        />
    </Transition>

    <!-- modal -->
    <Transition
        enter-active-class="ease-out duration-300"
        enter-from-class="opacity-0 scale-95"
        enter-to-class="opacity-100 scale-100"
        leave-active-class="ease-in duration-200"
        leave-from-class="opacity-100 scale-100"
        leave-to-class="opacity-0 scale-95"
    >
        <div
            v-if="show"
            class="relative bg-white rounded-lg shadow-xl w-full mx-4"
            :class="maxWidthClass"
        >
            <!-- HEADER -->
            <div class="flex justify-between items-center p-4 border-b">
                <h2 class="text-lg font-bold">{{ title }}</h2>
                <button @click="close">✕</button>
            </div>

            <!-- BODY -->
            <div class="p-4">
                <slot />
            </div>

            <!-- FOOTER -->
            <div class="p-4 border-t flex justify-end gap-2">
                <slot name="footer" />
            </div>
        </div>
    </Transition>

</div>
</template>
