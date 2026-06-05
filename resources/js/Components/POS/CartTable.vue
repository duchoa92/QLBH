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

const updateQty = (item, event) => {

    let qty = Number(event.target.value)

    if (isNaN(qty) || qty < 1) {

        qty = 1
    }

    item.quantity = qty
}
</script>

<template>

<div>

    <div
        v-for="item in items"
        :key="item.id"
        class="flex justify-between items-center py-2 border-b"
    >

        <div>
            <div class="text-sm font-medium">
                {{ item.name }}
            </div>

            <div class="text-xs text-gray-500">
                {{ item.price }}
            </div>
        </div>

        <div class="flex items-center gap-2">

            <button @click="item.qty--">-</button>

            <span>{{ item.qty }}</span>

            <button @click="item.qty++">+</button>

        </div>

    </div>

</div>

</template>
