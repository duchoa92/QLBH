<script setup>
import { useModal, closeModal } from '@/Stores/modal'

const { modals } = useModal()
</script>

<template>
     <div
        v-if="modals.length"
        class="fixed inset-0 z-[9999]"
    >
        <component
            v-for="modal in modals"
            :key="modal.id"
            :is="modal.component"
            v-bind="modal.props"
            :modalId="modal.id"  
            @close="closeModal(modal.id)"
            @updated="() => {
                modal.onUpdated && modal.onUpdated()
                closeModal(modal.id)
            }"
        />
    </div>
</template>