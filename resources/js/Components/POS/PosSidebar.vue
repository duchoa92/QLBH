<script setup>
import { ref, computed } from 'vue'
import CustomerSection from '@/Modules/POS/Customer/Components/CustomerSection.vue'
import CartTable from '@/Components/POS/CartTable.vue'
import PaymentMethodSelect from '@/Modules/POS/Payment/Components/PaymentMethodSelect.vue'

const props = defineProps({
    cart: { type: Array, default: () => [] },
    selectedCustomer: { type: Object, default: null },
    holdSales: { type: Array, default: () => [] },
    grandTotal: { type: Number, default: 0 },
    loading: Boolean,
})

const emit = defineEmits(['customer-selected', 'open-hold', 'show-hold-list', 'remove-item', 'checkout'])

const note = ref('')
const paymentMethod = ref('cash')
const paidAmount = ref('')

const changeAmount = computed(() => {
    const paid = Number(paidAmount.value) || 0
    return paid > props.grandTotal ? paid - props.grandTotal : 0
})

const formatMoney = (value) => Number(value || 0).toLocaleString('vi-VN')
</script>

<template>
    <aside class="flex flex-col h-[calc(100vh-64px)] w-full lg:w-[380px] bg-white border-l shadow-xl shrink-0">
        
        <div class="shrink-0 border-b border-gray-200 bg-gray-50 p-2">
            <CustomerSection
                :customer="selectedCustomer"
                @selected="$emit('customer-selected', $event)"
            />
        </div>

        <div class="flex-1 overflow-y-auto bg-white custom-scrollbar">
            <CartTable
                v-if="cart.length > 0"
                :items="cart"
                @remove="$emit('remove-item', $event)"
            />
            <div v-else class="h-full flex flex-col items-center justify-center text-gray-400 opacity-60">
                <i class="fa-solid fa-cart-shopping text-3xl mb-2"></i>
                <p class="text-sm">Giỏ hàng trống</p>
            </div>
        </div>

        <div class="shrink-0 border-t border-gray-200 bg-gray-50 p-2">
            <div class="flex justify-between items-center mb-2 px-1">
                <div class="flex gap-2">
                    <button @click="$emit('open-hold')" class="text-xs text-orange-600 font-bold hover:underline">F4 Lưu</button>
                    <button @click="$emit('show-hold-list')" class="text-xs text-indigo-600 font-bold hover:underline">Chờ ({{ holdSales.length }})</button>
                </div>
                <div class="text-right">
                    <span class="text-lg font-black text-blue-700">{{ formatMoney(grandTotal) }}</span>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-1.5 mb-2">
                <PaymentMethodSelect v-model="paymentMethod" class="h-9 text-sm rounded-lg" />
                <input v-model="paidAmount" type="number" placeholder="Khách đưa" class="h-9 px-3 border border-gray-300 rounded-lg text-sm shadow-sm" />
                <input v-model="note" type="text" placeholder="Ghi chú..." class="col-span-2 h-9 px-3 border border-gray-300 rounded-lg text-sm shadow-sm" />
            </div>

            <button
                :disabled="cart.length === 0 || loading"
                @click="$emit('checkout', { note, payment_method: paymentMethod, paid_amount: paidAmount })"
                class="w-full h-11 rounded-xl bg-blue-600 text-white font-bold text-sm hover:bg-blue-700 active:scale-[0.98] transition-all shadow-md disabled:bg-gray-300"
            >
                {{ loading ? 'ĐANG XỬ LÝ...' : 'THANH TOÁN (F9)' }}
                <span v-if="changeAmount > 0" class="ml-2 text-green-200 font-normal">
                    Thừa: {{ formatMoney(changeAmount) }}
                </span>
            </button>
        </div>
    </aside>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
</style>