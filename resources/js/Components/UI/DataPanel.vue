<script setup>
defineProps({
    detached: { type: Boolean, default: false },
    // Cho phép dropdown/popup đặt tuyệt đối tràn ra ngoài khung thay vì bị cắt.
    allowOverflow: { type: Boolean, default: false },
})
</script>

<template>
    <section :class="detached ? 'space-y-3' : [allowOverflow ? '' : 'overflow-hidden', 'rounded-xl border border-slate-200 bg-white shadow-sm']">
        <!-- Dạng detached: phần header (ô tìm kiếm/lọc) dính đỉnh vùng cuộn suốt chiều dài bảng bên dưới. -->
        <div
            v-if="$slots.header"
            :class="detached ? 'sticky z-20 focus-within:z-[29] transition-[top] duration-200 ease-out' : 'border-b border-slate-200 px-4 py-3'"
            :style="detached ? { top: 'var(--app-sticky-top, 0px)' } : undefined"
        >
            <slot name="header" />
        </div>

        <slot />

        <div v-if="$slots.footer" :class="detached ? '' : 'border-t border-slate-200'">
            <slot name="footer" />
        </div>
    </section>
</template>
