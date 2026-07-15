<script setup>
import { useModal, closeModal } from '@/Stores/modal'

const { modals } = useModal()
</script>

<template>
    <component
        v-for="(modal, index) in modals"
        :key="modal.id"
        :is="modal.component"
        v-bind="modal.props"
        @close="closeModal(index)"
        @updated="modal.onUpdated && modal.onUpdated()"
    />
</template>