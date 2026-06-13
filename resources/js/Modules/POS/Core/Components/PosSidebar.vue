<script setup>
import { ref } from 'vue'

import CustomerSection from '@/Modules/POS/Customer/Components/CustomerSection.vue'
import CartTable from '@/Modules/POS/Cart/Components/CartTable.vue'
import PaymentPanel from '@/Modules/POS/Payment/Components/PaymentPanel.vue'
import SaleHistoryModal from '@/Modules/POS/Sale/Components/SaleHistoryModal.vue'


const props = defineProps({
    cart: { type: Array, default: () => [] },
    selectedCustomer: { type: Object, default: null },
    holdSales: { type: Array, default: () => [] },
    grandTotal: { type: Number, default: 0 },
    loading: Boolean,
})

const showSaleHistory = ref(false)

const emit = defineEmits(['customer-selected', 'open-hold', 'show-hold-list', 'remove-item', 'checkout'])
// Ghi chú đơn hàng

</script>

<template>
    <aside class="flex flex-col h-[calc(100vh-64px)] w-full lg:w-[380px]
           bg-white border-l shadow-xl shrink-0
           rounded-2xl overflow-hidden">
        <!-- khối khách hàng -->
        <div class="shrink-0 border-b border-gray-200 bg-white p-2">
            <CustomerSection
                :customer="selectedCustomer"
                @selected="$emit('customer-selected', $event)"
            />
        </div>
        <!-- khối giỏ hàng -->
        <div class="flex-1 overflow-y-auto bg-gray-100 custom-scrollbar px-[4px] py-[2px]">
            <CartTable
                v-if="cart.length > 0"
                :items="cart"
                @remove="$emit('remove-item', $event)"
            />
            <div v-else class="h-full flex flex-col items-center justify-center text-gray-400 opacity-60 p-4">
                <i class="fa-solid fa-cart-shopping text-3xl mb-2"></i>
                <p class="text-sm">Giỏ hàng trống</p>
            </div>
        </div>
        
        <!--Khối thanh toán-->
        <PaymentPanel
            :cart="cart"
            :grand-total="grandTotal"
            :selected-customer="selectedCustomer"
            :hold-sales="holdSales"
            :loading="loading"

            @open-hold="$emit('open-hold')"

            @show-hold-list="$emit('show-hold-list')"

            @checkout="$emit('checkout', $event)"

            @show-sale-history="showSaleHistory = true"
        />

    </aside>

        <!--Lịch sử HĐ-->
        <SaleHistoryModal
            :show="showSaleHistory"
            @close="showSaleHistory = false"
        />


</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
</style>