<script setup>
import ProductSearch from '@/Modules/POS/Product/Components/ProductSearch.vue'

// Bổ sung defineProps để nhận cart từ trang POS chính
const props = defineProps({
    cart: {
        type: Array,
        default: () => [],
    },
})

const emit = defineEmits([
    'add-product',
])

const forwardProductSelection = (product, originRect = null) => {
    emit('add-product', product, originRect)
}
</script>

<template>
    <section class="min-h-0 w-full flex-1 overflow-hidden bg-transparent font-sans lg:col-span-7 xl:col-span-8">
        <div class="flex flex-col h-full min-h-0">

            <!-- BODY PANEL -->
            <div class="flex min-h-0 flex-1 flex-col overflow-hidden bg-transparent p-0">
                <ProductSearch
                    :cart="cart"
                    @selected="forwardProductSelection"
                />
            </div>

        </div>
    </section>
</template>
