<script setup>
import ProductSearch from '@/Modules/POS/Product/Components/ProductSearch.vue'
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
            <div class="flex items-center justify-between border-b border-slate-100 px-3 py-2.5 bg-white sm:px-5 sm:py-3.5">
                <div class="flex items-center gap-2.5">
                    <div class="rounded-xl bg-indigo-50 p-1.5 text-indigo-600 sm:p-2">
                        <PackageSearch class="h-4 w-4 sm:h-5 sm:w-5" />
                    </div>
                    <div>
                        <h1 class="text-sm font-bold leading-tight text-slate-900 sm:text-base">
                            Chọn & Tìm kiếm Sản phẩm
                        </h1>
                        <p class="text-[10px] text-slate-400 sm:text-xs">Gõ tên, mã vạch hoặc IMEI để thêm vào đơn</p>
                    </div>
                </div>

                <div class="rounded-xl bg-emerald-50 border border-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700 flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    POS Bán hàng
                </div>
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
