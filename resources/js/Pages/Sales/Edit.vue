<script setup>
import { router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import SaleEditModal from '@/Components/SaleEditModal.vue'

defineOptions({ layout: AdminLayout })

defineProps({
    sale: { type: Object, required: true },
    blockedReason: { type: String, default: '' },
})

const returnToSales = () => router.visit(route('sales.index'))
</script>

<template>
    <SaleEditModal
        :sale="sale"
        :blocked-reason="blockedReason"
        @close="returnToSales"
        @saved="returnToSales"
    />
</template>
