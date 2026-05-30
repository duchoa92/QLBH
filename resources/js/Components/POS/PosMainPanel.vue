<script setup>

import ProductSearch from '@/Modules/POS/Product/Components/ProductSearch.vue'
import CartTable from '@/Components/POS/CartTable.vue'
import { useBarcodeScanner } from '@/Modules/POS/Product/Composables/useBarcodeScanner'
import { productService } from '@/Modules/POS/Product/Services/productService'

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

/*
|--------------------------------------------------------------------------
| BARCODE SCANNER
|--------------------------------------------------------------------------
*/

useBarcodeScanner(

    async (barcode) => {

        try {

            const product =
                await productService
                    .findByBarcode(
                        barcode
                    )

            if (!product) {

                return
            }

            emit(
                'add-product',
                product
            )

        } catch (error) {

            console.error(error)
        }
    }
)


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