<script setup>
import { ref } from 'vue'

import CustomerSection from '@/Modules/POS/Customer/Components/CustomerSection.vue'
import CartTable from '@/Modules/POS/Cart/Components/CartTable.vue'
import PaymentPanel from '@/Modules/POS/Payment/Components/PaymentPanel.vue'
import SaleHistoryModal from '@/Modules/POS/Sale/Components/SaleHistoryModal.vue'
import CartTabs from './CartTabs.vue'

const props = defineProps({
    tabs: {
        type: Array,
        default: () => [],
    },
    activeTabId: {
        type: Number,
        default: null,
    },
    cart: { type: Array, default: () => [] },
    selectedCustomer: { type: Object, default: null },
    grandTotal: { type: Number, default: 0 },
    loading: Boolean,
})

const showSaleHistory = ref(false)

const emit = defineEmits([
    'customer-selected',
    'remove-item',
    'checkout',
    'select-tab',
    'create-tab',
    'remove-tab',
])
</script>

<template>
    <aside class="flex flex-col h-[calc(100vh-2rem)] w-full lg:w-[430px] bg-white border border-slate-200/80 shadow-lg shrink-0 rounded-2xl overflow-hidden font-sans antialiased">
        <!-- Khối trên: Tab đơn hàng & Tìm/Chọn khách hàng -->
        <div class="shrink-0 border-b border-slate-100 bg-white p-3 space-y-2.5">
            <CartTabs
                :tabs="tabs"
                :active-tab-id="activeTabId"
                @select="$emit('select-tab', $event)"
                @create="$emit('create-tab')"
                @remove="$emit('remove-tab', $event)"
            />
            
            <CustomerSection
                :customer="selectedCustomer"
                @selected="$emit('customer-selected', $event)"
            />
        </div>

        <!-- Khối giữa: Bảng Giỏ Hàng -->
        <div class="flex-1 overflow-y-auto bg-slate-50/60 custom-scrollbar p-2">
            <CartTable
                v-if="cart.length > 0"
                :items="cart"
                @remove="$emit('remove-item', $event)"
            />
            
            <div v-else class="h-full flex flex-col items-center justify-center text-slate-400 p-6 select-none">
                <div class="w-16 h-16 rounded-2xl bg-slate-100 flex items-center justify-center mb-3 text-slate-300">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z" />
                    </svg>
                </div>
                <p class="text-sm font-medium text-slate-500">Giỏ hàng đang trống</p>
                <p class="text-xs text-slate-400 mt-1">Vui lòng chọn hoặc quét mã sản phẩm</p>
            </div>
        </div>
        
        <!-- Khối dưới: Thanh toán & Tính tiền -->
        <div class="shrink-0 border-t border-slate-100 bg-white p-3">
            <PaymentPanel
                :cart="cart"
                :grand-total="grandTotal"
                :selected-customer="selectedCustomer"
                :loading="loading"
                @checkout="$emit('checkout', $event)"
                @show-sale-history="showSaleHistory = true"
            />
        </div>
    </aside>

    <!-- Modal Lịch sử đơn hàng -->
    <SaleHistoryModal
        :show="showSaleHistory"
        @close="showSaleHistory = false"
    />
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
</style>