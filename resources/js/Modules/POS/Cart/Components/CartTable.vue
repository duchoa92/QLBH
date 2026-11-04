<script setup>
import CartItem from './CartItem.vue'

const props = defineProps({

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


</script>

<template>
    <CartItem
        v-for="item in items"
        :key="
            item.imei_id
                ? 'imei-' + item.imei_id
                : 'product-' + item.id + '-variant-' + (item.variant_id ?? 'default')
        "
        :item="item"
        :cart="items"
        @remove="
            emit(
                'remove',
                $event
            )
        "
    />  
</template>

