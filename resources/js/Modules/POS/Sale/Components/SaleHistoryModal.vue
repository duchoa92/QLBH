<script setup>

import InvoiceList from './InvoiceList.vue'


defineProps({

    show: {
        type: Boolean,
        default: false,
    },
})

const emit = defineEmits([
    'close',
])

</script>

<template>

<div
    v-if="show"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-2 sm:p-4"
>

    <div
        class="flex h-[min(82dvh,42rem)] max-h-[92dvh] w-full max-w-4xl flex-col overflow-hidden rounded-xl bg-white shadow-2xl"
    >

        <div
            class="flex shrink-0 items-center justify-between border-b border-slate-200 px-4 py-3"
        >

            <h2 class="font-bold text-lg">
                Lịch sử giao dịch
            </h2>

            <button
                @click="emit('close')"
                type="button"
                aria-label="Đóng lịch sử giao dịch"
                class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-800"
            >
                X
            </button>

        </div>

        <div class="min-h-0 flex-1 overflow-hidden">

            <InvoiceList />

        </div>

    </div>

</div>

</template>
