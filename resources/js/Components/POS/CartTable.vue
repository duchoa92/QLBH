<script setup>


defineProps({

    selectedIndex: {
        type: Number,
        default: -1,
    },

    items: {
        type: Array,
        default: () => [],
    },
})

const emit = defineEmits([
    'remove',
])

const format = (number) => {

    return Number(number || 0)
        .toLocaleString('vi-VN')
}

</script>

<template>

<div>

    <div
        v-for="item in items"
        :key="item.imei_id ? 'imei-' + item.imei_id : 'product-' + item.id"
        class="bg-white border border-gray-300 rounded-xl px-3 py-2 mb-[4px] shadow-sm"
    >

        <div class="flex justify-between items-start">
            <div class="flex-1 min-w-0">
                <div class="font-medium text-sm truncate">
                    {{ item.name }}
                </div>

                <div class="text-xs text-gray-500">
                    <span v-if="item.imei">
                        IMEI:
                        {{ item.imei }}
                    </span>
                </div>

                <div class="text-xs text-gray-500">
                    {{ format(item.price) }} ₫
                </div>
            </div>

            <div class="flex items-center gap-2 ml-3">

                <button
                    class="w-7 h-7 rounded-full border bg-gray-100 hover:bg-gray-200 shadow-sm"
                    @click="
                        item.quantity > 1
                            ? item.quantity--
                            : emit('remove', item)
                    "
                    :disabled="Boolean(item.imei_id)"
                >
                    -
                </button>

                <input
                    v-model="item.quantity"
                    :disabled="Boolean(item.imei_id)"
                    class="w-10 items-center"
                />

                <button class="w-7 h-7 rounded-full border bg-gray-100 hover:bg-gray-200 shadow-sm"
                    @click="
                        !item.imei_id &&
                        item.quantity++
                    "
                    :disabled="Boolean(item.imei_id)"
                >
                    +
                </button>

                <button
                    class="text-red-500 hover:text-red-600 shadow-sm"
                    @click="emit('remove', item)"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-trash2 lucide-trash-2 size-4" aria-hidden="true"><path d="M10 11v6"></path><path d="M14 11v6"></path><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path><path d="M3 6h18"></path><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                </button>

            </div>
        </div>

    </div>

</div>

</template>
