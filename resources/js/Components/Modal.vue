<script setup>
import { useModal } from '@/Stores/modal'
import { onMounted, onBeforeUnmount } from 'vue'


const { modals } = useModal()

const handleKey = (e) => {
    if (e.key === 'Escape') closeModal()
}


onMounted(() => window.addEventListener('keydown', handleKey))
onBeforeUnmount(() => window.removeEventListener('keydown', handleKey))
</script>

<template>
<div v-if="modalState.show">

    <!-- OVERLAY -->
    <div 
        class="fixed inset-0 bg-black/50 z-[999]"
        @click="closeModal"
    ></div>

    <!-- MODAL -->
    <div class="fixed inset-0 z-[1000] flex items-center justify-center pointer-events-none">

        <div 
            class="bg-white w-[600px] rounded shadow-lg pointer-events-auto"
            @click.stop
        >
            
            <!-- HEADER -->
            <div class="flex justify-between items-center p-4 border-b">
                <h2 class="font-bold">{{ modalState.title }}</h2>
                <button @click="closeModal">✕</button>
            </div>

            <!-- BODY -->
            <div class="p-4">
                <component 
                    :is="modalState.component"
                    v-bind="modalState.props"
                    @close="closeModal"
                    @updated="modalState.onUpdated && modalState.onUpdated()"
                />
            </div>

        </div>

    </div>
</div>
</template>

<style>
@keyframes scaleIn {
  from { transform: scale(0.95); opacity: 0 }
  to { transform: scale(1); opacity: 1 }
}

.animate-scale {
  animation: scaleIn 0.15s ease;
}
</style>