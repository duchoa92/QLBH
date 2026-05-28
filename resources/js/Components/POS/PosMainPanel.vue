<script setup>

import ProductSearch from '@/Components/POS/ProductSearch.vue'

import CartTable from '@/Components/POS/CartTable.vue'

const props = defineProps({

    cart: {
        type: Array,
        default: () => [],
    },

    selectedCartIndex: {
        type: Number,
        default: 0,
    },
})

const emit = defineEmits([

    'add-product',

    'remove-item',
])
</script>

<template>

    <div
        class="col-span-8 bg-white rounded shadow p-3 flex flex-col"
    >

        <!-- Search -->
        <ProductSearch
            @selected="
                emit(
                    'add-product',
                    $event
                )
            "
        />

        <!-- Cart -->
        <div
            class="mt-3 flex-1 overflow-auto"
        >

            <CartTable

                :items="cart"

                :selected-index="
                    selectedCartIndex
                "

                @remove="
                    emit(
                        'remove-item',
                        $event
                    )
                "
            />

        </div>

    </div>

</template>