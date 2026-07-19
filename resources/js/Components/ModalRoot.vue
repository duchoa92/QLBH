<script setup>
import { useModal, closeModal } from '@/Stores/modal'

const state = useModal()

const handleClose = (id) => {
    closeModal(id)
}
</script>

<template>
<div v-if="state.modals.length" class="fixed inset-0 z-[9999]">

    
    <!-- stack modal -->
    <component
        :is="state.modals[state.modals.length - 1].component"
        :key="state.modals[state.modals.length - 1].id"
        v-bind="state.modals[state.modals.length - 1].props"
        :modalId="state.modals[state.modals.length - 1].id"
        @close="handleClose(state.modals[state.modals.length - 1].id)"
        @updated="state.modals[state.modals.length - 1].onUpdated?.()"
    />

</div>
</template>