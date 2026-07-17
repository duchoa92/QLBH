<script setup>
import { useConfirm } from '@/Composables/useConfirm'

const { state, confirm, cancel } = useConfirm()



</script>

<template>
<div v-if="state.show"
     class="fixed inset-0 z-[10000] flex items-center justify-center bg-black/40">
    <div class="bg-white p-6 rounded shadow w-[400px] pointer-events-auto">

        <h2 class="font-bold text-lg mb-2">
            {{ state.title }}
        </h2>

        <p class="text-gray-600 mb-4">
            {{ state.message }}
        </p>

        <div class="flex justify-end gap-2">
            <button @click="cancel" class="px-4 py-2 bg-gray-200 rounded">
                {{ state.cancelText }}
            </button>

            <button @click="confirm" class="px-4 py-2 bg-red-600 text-white rounded">
                {{ state.confirmText }}
            </button>
        </div>

    </div>
</div>
</template>