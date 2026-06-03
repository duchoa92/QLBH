<script setup>

import AdminLayout from '@/Layouts/AdminLayout.vue'

</script>

<template>

    <AdminLayout>

        <div
            v-if="$slots.header"
            class="mb-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm"
        >
            <slot name="header" />
        </div>

        <slot />

    </AdminLayout>

</template>
