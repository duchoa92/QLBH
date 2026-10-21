<script setup>
defineProps({
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        default: '',
    },
})
</script>

<template>
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="min-w-0">
            <h1 class="text-lg font-bold leading-6 text-slate-900">
                {{ title }}
            </h1>

            <p
                v-if="description"
                class="mt-0.5 text-xs text-slate-500"
            >
                {{ description }}
            </p>
        </div>

        <div
            v-if="$slots.actions"
            class="flex flex-wrap items-center gap-1.5"
        >
            <slot name="actions" />
        </div>
    </div>
</template>
