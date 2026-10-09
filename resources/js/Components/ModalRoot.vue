<script setup>
import { computed } from 'vue'
import { useModal, closeModal } from '@/Stores/modal'

const state = useModal()
const activeModal = computed(() => state.modals.at(-1) || null)

const handleClose = () => {
    if (activeModal.value) closeModal(activeModal.value.id)
}

const handleUpdated = (payload) => {
    activeModal.value?.onUpdated?.(payload)
}
</script>

<template>
<div v-if="activeModal" class="contents">
    <component
        :is="activeModal.component"
        :key="activeModal.id"
        v-bind="activeModal.props"
        :modalId="activeModal.id"
        @close="handleClose"
        @updated="handleUpdated"
    />
</div>
</template>
