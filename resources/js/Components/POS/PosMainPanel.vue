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

        <div class="flex h-full min-h-0 flex-col">

            <div
                class="flex items-center justify-between border-b border-slate-200 px-4 py-3"
            >

                <div>

                    <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Kho hang
                    </div>

                    <h1 class="text-lg font-bold text-slate-900">
                        Chon san pham
                    </h1>

                </div>

                <div class="rounded-md bg-emerald-50 px-3 py-1 text-sm font-semibold text-emerald-700">
                    POS ban hang
                </div>

            </div>

            <ProductSearch
                @selected="
                    emit(
                        'add-product',
                        $event
                    )
                "
            />

        </div>

    </section>

</template>
