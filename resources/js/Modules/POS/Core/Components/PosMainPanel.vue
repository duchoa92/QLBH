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
    <section class="min-h-0 rounded-2xl border border-slate-200/80 bg-white shadow-sm lg:col-span-7 xl:col-span-8 overflow-hidden font-sans">
        <div class="flex flex-col h-full min-h-0">

            <!-- HEADER POS PANEL -->
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3.5 bg-white">
                <div class="flex items-center gap-2.5">
                    <div class="p-2 rounded-xl bg-indigo-50 text-indigo-600">
                        <PackageSearch class="w-5 h-5" />
                    </div>
                    <div>
                        <h1 class="text-base font-bold text-slate-900 leading-tight">
                            Chọn & Tìm kiếm Sản phẩm
                        </h1>
                        <p class="text-xs text-slate-400">Gõ tên, mã vạch hoặc IMEI để thêm vào đơn</p>
                    </div>
                </div>

                <div class="rounded-xl bg-emerald-50 border border-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700 flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    POS Bán hàng
                </div>
            </div>

            <!-- BODY PANEL -->
            <div class="flex-1 min-h-0 lg:overflow-auto p-4 bg-slate-50/40">
                <ProductSearch
                    :cart="cart"
                    @selected="emit('add-product', $event)"
                />
            </div>

        </div>
    </section>
</template>