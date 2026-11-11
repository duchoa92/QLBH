<script setup>

import AdminLayout from '@/Layouts/AdminLayout.vue'

</script>

<template>

    <AdminLayout>

        <div
            v-if="$slots.header"
            class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"
        >
            <slot name="header" />
        </div>

        <slot />

    </AdminLayout>

</template>
