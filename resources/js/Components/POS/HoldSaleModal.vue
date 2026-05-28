<script setup>

defineProps({

    show: {
        type: Boolean,
        default: false,
    },

    holdSales: {
        type: Array,
        default: () => [],
    },
})

defineEmits([
    'close',
    'load',
])

</script>

<template>

    <div
        v-if="show"
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
    >

        <div class="bg-white w-[500px] rounded p-4">

            <div class="flex justify-between mb-4">

                <h2 class="text-lg font-bold">
                    Hóa đơn giữ
                </h2>

                <button
                    @click="$emit('close')"
                >
                    X
                </button>

            </div>

            <div
                v-for="hold in holdSales"
                :key="hold.id"
                class="border rounded p-3 mb-2"
            >

                <div class="font-bold">
                    {{ hold.name }}
                </div>

                <div>
                    {{ hold.grand_total }}
                </div>

                <button
                    @click="$emit('load', hold.id)"
                    class="mt-2 px-3 py-1 bg-blue-500 text-white rounded"
                >
                    Mở lại
                </button>

            </div>

        </div>

    </div>

</template>