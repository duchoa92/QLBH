<template>
    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div
            v-if="$slots.header"
            class="border-b border-slate-200 px-4 py-3"
        >
            <slot name="header" />
        </div>

        <slot />

        <div v-if="$slots.footer" class="border-t border-slate-200">
            <slot name="footer" />
        </div>
    </section>
</template>
