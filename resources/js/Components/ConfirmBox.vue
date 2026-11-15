<script setup>
import { useConfirm } from '@/Composables/useConfirm'

const { state, confirm, cancel } = useConfirm()



</script>

<template>
<div v-if="state.show"
     class="fixed inset-0 z-[10000] flex items-center justify-center bg-black/40 p-4">
    <div class="bg-white p-5 sm:p-6 rounded-xl shadow w-[400px] max-w-full max-h-[calc(100dvh-2rem)] overflow-y-auto pointer-events-auto">

        <h2 class="font-bold text-lg mb-2">
            {{ state.title }}
        </h2>

        <p class="text-gray-600 mb-4">
            {{ state.message }}
        </p>

        <p v-if="state.error" class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">
            {{ state.error }}
        </p>

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <button :disabled="state.confirming" @click="cancel" class="min-h-11 px-4 py-2 bg-gray-200 rounded sm:min-h-0 disabled:cursor-not-allowed disabled:opacity-50">
                {{ state.cancelText }}
            </button>

            <button :disabled="state.confirming" @click="confirm" class="min-h-11 px-4 py-2 bg-red-600 text-white rounded sm:min-h-0 disabled:cursor-wait disabled:opacity-60">
                {{ state.confirming ? 'Đang xử lý...' : state.confirmText }}
            </button>
        </div>

    </div>
</div>
</template>
