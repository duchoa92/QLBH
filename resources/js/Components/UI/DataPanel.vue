<script setup>
defineProps({
    detached: { type: Boolean, default: false },
    // Cho phép dropdown/popup đặt tuyệt đối tràn ra ngoài khung thay vì bị cắt.
    allowOverflow: { type: Boolean, default: false },
})
</script>

<template>
    <section :class="detached ? 'space-y-3' : [allowOverflow ? '' : 'overflow-hidden', 'rounded-xl border border-slate-200 bg-white shadow-sm']">
        <div
            v-if="$slots.header"
            :class="detached ? '' : 'border-b border-slate-200 px-4 py-3'"
        >
            <slot name="header" />
        </div>

        <slot />

        <div v-if="$slots.footer" :class="detached ? '' : 'border-t border-slate-200'">
            <slot name="footer" />
        </div>
    </section>
</template>
