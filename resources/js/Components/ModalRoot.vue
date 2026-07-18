<script setup>
import { useModal, closeModal } from '@/Stores/modal'
import { onMounted, onBeforeUnmount } from 'vue'

const handleEsc = (e) => {
    if (e.key === 'Escape' && state.modals.length) {
        closeModal() // đóng modal trên cùng
    }
}

onMounted(() => {
    window.addEventListener('keydown', handleEsc)
})

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleEsc)
})

const state = useModal()

const handleClose = (modalId) => {
    closeModal(modalId)
}
</script>

<template>
    <div v-if="state.modals.length" class="fixed inset-0 z-[9999]">
        <component
            :is="state.modals[state.modals.length - 1].component"
            :key="state.modals[state.modals.length - 1].id"
            v-bind="state.modals[state.modals.length - 1].props"
            :modalId="state.modals[state.modals.length - 1].id"
            @close="handleClose(state.modals[state.modals.length - 1].id)"
            @updated="state.modals[state.modals.length - 1].onUpdated ? state.modals[state.modals.length - 1].onUpdated() : null"
        />
    </div>
</template>