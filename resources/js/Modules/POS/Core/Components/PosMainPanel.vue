<script setup>
import ProductSearch from '@/Modules/POS/Product/Components/ProductSearch.vue'
import OfflineStatus from '@/Components/OfflineStatus.vue'
import { PackageSearch } from 'lucide-vue-next'

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
</script>

<template>
    <section class="min-h-0 w-full flex-1 rounded-2xl border border-slate-200/80 bg-white shadow-sm lg:col-span-7 xl:col-span-8 overflow-hidden font-sans">
        <div class="flex flex-col h-full min-h-0">

            <!-- HEADER POS PANEL -->
            <div class="flex items-center justify-between gap-2 border-b border-slate-100 bg-white px-3 py-2 sm:px-5 sm:py-2.5">
                <div class="flex min-w-0 flex-1 items-center gap-2.5">
                    <div class="rounded-xl bg-blue-50 p-1.5 text-blue-600 sm:p-2">
                        <PackageSearch class="h-4 w-4 sm:h-5 sm:w-5" />
                    </div>
                    <div class="min-w-0">
                        <h1 class="truncate text-sm font-bold leading-tight text-slate-900 sm:text-base">
                            Chọn & Tìm kiếm Sản phẩm
                        </h1>
                    </div>
                </div>

                <OfflineStatus />
            </div>

            <!-- BODY PANEL -->
            <div class="flex min-h-0 flex-1 flex-col overflow-hidden bg-slate-50/40 p-2.5 sm:p-4">
                <ProductSearch
                    :cart="cart"
                    @selected="emit('add-product', $event)"
                />
            </div>

        </div>
    </section>
</template>
