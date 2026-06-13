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
    class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center"
>

    <div
        class="bg-white w-[1000px] h-[80vh] rounded-xl overflow-hidden"
    >

        <div
            class="flex justify-between items-center p-4 border-b"
        >

            <h2 class="font-bold text-lg">
                Lịch sử giao dịch
            </h2>

            <button
                @click="emit('close')"
                class="text-red-500 font-bold"
            >
                X
            </button>

        </div>

        <div class="h-[calc(90vh-65px)] overflow-auto">

            <InvoiceList />

        </div>

    </div>

</div>

</template>