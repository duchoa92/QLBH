<script setup>
import { modalState, closeModal } from '@/Stores/modal'
</script>

<template>
<div v-if="modalState.show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
    
    <div class="bg-white w-[600px] rounded shadow-lg">
        
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
</template>