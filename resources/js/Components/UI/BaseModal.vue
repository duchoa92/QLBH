<script setup>

import { onMounted, onBeforeUnmount } from 'vue'

const props = defineProps({
    title: String,
    size: {
        type: String,
        default: 'lg' // sm | md | lg | xl
    },
    bodyClass: {
        type: String,
        default: 'p-4',
    },
    embedded: {
        type: Boolean,
        default: false,
    },
})

const emit = defineEmits(['close', 'updated'])

const handleEsc = (e) => {
    if (e.key === 'Escape') {
        emit('close')
    }
}

onMounted(() => {
    if (!props.embedded) window.addEventListener('keydown', handleEsc)
})

onBeforeUnmount(() => {
    if (!props.embedded) window.removeEventListener('keydown', handleEsc)
})

</script>

<template>
    <template v-if="embedded">
        <div :class="bodyClass"><slot /></div>
    </template>
    <Teleport v-else to="body">
    <div class="fixed inset-0 z-[1000] flex items-center justify-center overflow-y-auto p-3 sm:p-5">
        <div
            class="absolute inset-0 bg-slate-950/50 backdrop-blur-[2px]"
            @click="$emit('close')"
        ></div>

        <section
            role="dialog"
            aria-modal="true"
            class="relative flex min-h-0 max-h-[calc(100dvh-1.5rem)] w-full flex-col overflow-hidden rounded-xl bg-white shadow-2xl ring-1 ring-slate-900/10 sm:max-h-[calc(100dvh-2.5rem)]"
            :class="{
                'max-w-md': size === 'sm',
                'max-w-xl': size === 'md',
                'max-w-3xl': size === 'lg',
                'max-w-4xl': size === 'xl'
            }"
        >
            <!-- HEADER -->
            <div class="flex shrink-0 items-center justify-between border-b border-slate-200 bg-slate-50 px-4 py-3">
                <h2 class="text-base font-bold text-slate-900">{{ title }}</h2>
                <button
                    type="button"
                    @click="$emit('close')"
                    class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:bg-white hover:text-red-500"
                    aria-label="Đóng"
                >
                    ✕
                </button>
            </div>

            <!-- BODY -->
            <div class="min-h-0 flex-1 overflow-y-auto bg-white" :class="bodyClass">
                <slot />
            </div>

            <!-- FOOTER -->
            <div v-if="$slots.footer" class="shrink-0 border-t border-slate-200 bg-slate-50 px-4 py-3">
                <slot name="footer" />
            </div>

        </section>
    </div>
    </Teleport>
</template>
