<script setup>

import ProductSearch from '@/Modules/POS/Product/Components/ProductSearch.vue'

const emit = defineEmits([

    'add-product',
])

</script>

<template>

    <section
        class="min-h-0 rounded-lg border border-slate-200 bg-white shadow-sm lg:col-span-7 xl:col-span-8"
    >

       <div class="flex flex-col h-full min-h-0">

            <!-- HEADER (không scroll) -->
            <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                <h1 class="text-lg font-bold text-slate-900">
                    Chọn sản phẩm
                </h1>

                <div class="rounded-md bg-emerald-50 px-3 py-1 text-sm font-semibold text-emerald-700">
                    POS bán hàng
                </div>
            </div>

            <!-- BODY (scroll chỉ ở desktop) -->
            <div class="flex-1 min-h-0 lg:overflow-auto">

                <ProductSearch
                    @selected="emit('add-product', $event)"
                />

            </div>

        </div>

    </section>

</template>
