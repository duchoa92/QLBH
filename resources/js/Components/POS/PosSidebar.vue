<script setup>
import { ref } from 'vue'
import CustomerSection from '@/Modules/POS/Customer/Components/CustomerSection.vue'
import KeyboardShortcuts from '@/Components/POS/KeyboardShortcuts.vue'
import CartTable from '@/Components/POS/CartTable.vue'
import PaymentMethodSelect from '@/Modules/POS/Payment/Components/PaymentMethodSelect.vue'

defineProps({

    cart: {
        type: Array,
        default: () => [],
    },

    selectedCustomer: {
        type: Object,
        default: null,
    },

    showShortcuts: {
        type: Boolean,
        default: false,
    },

    holdSales: {
        type: Array,
        default: () => [],
    },

    grandTotal: Number,
    loading: Boolean,
})

defineEmits([

    'customer-selected',

    'toggle-shortcuts',

    'open-hold',

    'show-hold-list',

    'remove-item',

    'checkout',
])


const note = ref('')
const paymentMethod = ref('cash')
const paidAmount = ref('')

</script>

<template>

    <aside class="rounded-lg border bg-white shadow-sm h-full flex flex-col min-h-0">

        <!-- HEADER -->
        <div class="border-b px-4 py-3 flex justify-between items-center">
            <h2 class="text-lg font-bold">Đơn hàng</h2>
            <span class="bg-gray-100 px-3 py-1 rounded text-sm">
                {{ cart.length }} mặt hàng
            </span>
        </div>

        <!-- BODY (SCROLL ONLY DESKTOP) -->
        <div class="flex-1 min-h-0 lg:overflow-auto">

            <!-- CUSTOMER + ACTION -->
            <div class="space-y-3 border-b p-4">

                <CustomerSection
                    :customer="selectedCustomer"
                    @selected="$emit('customer-selected', $event)"
                />

                <div class="grid grid-cols-2 gap-2">
                    <button @click="$emit('open-hold')" class="btn-yellow">
                        Lưu tạm
                    </button>

                    <button
                        :disabled="holdSales.length === 0"
                        @click="$emit('show-hold-list')"
                        class="btn-blue"
                    >
                        Hóa đơn đã lưu ({{ holdSales.length }})
                    </button>
                </div>

                <KeyboardShortcuts
                    :show="showShortcuts"
                    @toggle="$emit('toggle-shortcuts')"
                />

            </div>

            <!-- CART -->
            <CartTable
                :items="cart"
                @remove="$emit('remove-item', $event)"
            />

            <div>
                <input
                    v-model="paidAmount"
                    type="number"
                    placeholder="Tiền khách đưa"
                    class="w-full border rounded p-2 text-sm"
                />
            </div>
            <!-- NOTE + PAYMENT -->
            <div class="p-4 space-y-3 border-t">

                <!-- Chọn phương thức thanh toán -->
                <PaymentMethodSelect v-model="paymentMethod" />

                <textarea
                    v-model="note"
                    placeholder="Ghi chú..."
                    class="w-full border rounded p-2 text-sm"
                />

            </div>

        </div>

        <!-- FOOTER (FIXED) -->
        <div class="p-4 border-t bg-white lg:sticky lg:bottom-0">

            <button
                :disabled="cart.length === 0 || loading"
                @click="$emit('checkout', {note, paymentMethod, paidAmount })"
                class="w-full py-3 rounded font-semibold flex justify-between items-center px-4 active:scale-95 transition"
                :class="[
                    cart.length === 0
                        ? 'bg-gray-300 text-gray-500'
                        : 'bg-blue-600 text-white hover:bg-blue-700',
                    loading ? 'opacity-50' : ''
                ]"
            >

                <span>
                    {{
                        loading
                            ? 'Đang xử lý...'
                            : cart.length === 0
                                ? 'Chưa có sản phẩm'
                                : '💳 Thanh toán'
                    }}
                </span>

                <span v-if="cart.length">
                    {{ Number(grandTotal).toLocaleString() }} đ
                </span>

            </button>

        </div>

    </aside>
    

</template>
