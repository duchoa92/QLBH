<script setup>
const props = defineProps({
    variant: {
        type: String,
        default: 'primary',
    },
    type: {
        type: String,
        default: 'button',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
})
</script>

<template>
    <button
        :type="type"
        :disabled="disabled"
        class="inline-flex items-center justify-center gap-2 rounded-lg px-3.5 py-2 text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-50"
        :class="{
            'bg-emerald-600 text-white shadow-sm hover:bg-emerald-700': props.variant === 'primary',
            'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50': props.variant === 'secondary',
            'bg-red-600 text-white shadow-sm hover:bg-red-700': props.variant === 'danger',
            'bg-blue-600 text-white shadow-sm hover:bg-blue-700': props.variant === 'info',
            'bg-amber-500 text-white shadow-sm hover:bg-amber-600': props.variant === 'warning',
            'p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-800': props.variant === 'ghost',
        }"
    >
        <slot />
    </button>
</template>
