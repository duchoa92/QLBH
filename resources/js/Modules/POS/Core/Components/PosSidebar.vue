<script setup>
import { computed, ref, watch } from 'vue'
import { History, ChevronDown } from 'lucide-vue-next'
import FloatingInput from '@/Components/UI/FloatingInput.vue'

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
    totalQuantity: { type: Number, default: 0 },
    subTotal: { type: Number, default: 0 },
    discount: { type: Number, default: 0 },
    vat: { type: Number, default: 0 },
    selectedCustomer: { type: Object, default: null },
    grandTotal: { type: Number, default: 0 },
    invoiceDiscount: { type: Number, default: 0 },
    invoiceDiscountType: { type: String, default: 'amount' },
    extraFee: { type: Number, default: 0 },
    extraFeeType: { type: String, default: 'amount' },
    loading: Boolean,
})

const showSaleHistory = ref(false)

// Ô "Phí phụ thu & Chiết khấu đơn": ẩn/hiện, nhớ trạng thái cho lần sau.
const ADJUST_STORAGE_KEY = 'pos.showFeeDiscount'
const readAdjustState = () => {
    try { return localStorage.getItem(ADJUST_STORAGE_KEY) === '1' } catch { return false }
}
const showFeeDiscount = ref(readAdjustState())
watch(showFeeDiscount, (value) => {
    try { localStorage.setItem(ADJUST_STORAGE_KEY, value ? '1' : '0') } catch { /* bỏ qua */ }
})
const hasFeeOrInvoiceDiscount = computed(() => Number(props.extraFee || 0) > 0 || Number(props.invoiceDiscount || 0) > 0)
const needsCustomerForWarranty = computed(() => !props.selectedCustomer && props.cart.some((item) =>
    (item.imei_id || item.imei || item.serial)
    && Number(item.customer_warranty_days ?? item.warranty_days ?? 0) > 0
))

const emit = defineEmits([
    'customer-selected',
    'remove-item',
    'checkout',
    'select-tab',
    'create-tab',
    'remove-tab',
    'update:invoice-discount',
    'update:invoice-discount-type',
    'update:extra-fee',
    'update:extra-fee-type',
])

const discountInputValue = computed(() => {
    const value = Number(props.invoiceDiscount || 0)
    return props.invoiceDiscountType === 'percent' ? (value || '') : value
})

const updateInvoiceDiscount = inputValue => {
    const raw = String(inputValue || '')
    const parsedDiscount = props.invoiceDiscountType === 'percent'
        ? Math.min(100, Number(raw.replace(',', '.').replace(/[^\d.]/g, '')))
        : Number(raw || 0)
    emit('update:invoice-discount', Number.isFinite(parsedDiscount) ? Math.max(0, parsedDiscount) : 0)
}

const updateExtraFee = value => {
    const raw = String(value || '')
    const parsed = props.extraFeeType === 'percent'
        ? Number(raw.replace(',', '.').replace(/[^\d.]/g, ''))
        : Number(raw || 0)
    emit('update:extra-fee', Number.isFinite(parsed) ? Math.max(0, parsed) : 0)
}

const extraFeeInputValue = computed(() => props.extraFee ? props.extraFee : '')
const feeBase = computed(() => {
    const base = Math.max(0, props.subTotal - props.discount)
    const value = Math.max(0, Number(props.invoiceDiscount || 0))
    const invoiceDiscountAmount = props.invoiceDiscountType === 'percent'
        ? base * Math.min(100, value) / 100
        : Math.min(base, value)
    return Math.max(0, base - invoiceDiscountAmount)
})
const toggleExtraFeeType = () => {
    const current = Math.max(0, Number(props.extraFee || 0))
    if (props.extraFeeType === 'percent') {
        emit('update:extra-fee', Math.round(feeBase.value * current / 100))
        emit('update:extra-fee-type', 'amount')
    } else {
        emit('update:extra-fee', feeBase.value > 0 ? Number((current / feeBase.value * 100).toFixed(2)) : 0)
        emit('update:extra-fee-type', 'percent')
    }
}

const toggleInvoiceDiscountType = () => {
    const base = Math.max(0, props.subTotal - props.discount)
    const current = Math.max(0, Number(props.invoiceDiscount || 0))
    if (props.invoiceDiscountType === 'percent') {
        emit('update:invoice-discount', Math.round(base * Math.min(100, current) / 100))
        emit('update:invoice-discount-type', 'amount')
        return
    }
    emit('update:invoice-discount', base > 0 ? Math.min(100, Number((current / base * 100).toFixed(2))) : 0)
    emit('update:invoice-discount-type', 'percent')
}
</script>

<template>
    <aside class="flex h-full min-h-0 w-full shrink-0 flex-col overflow-hidden rounded-t-2xl border border-slate-200/80 bg-white shadow-lg font-sans antialiased lg:rounded-b-none">
        <!-- Khối trên: Tab đơn hàng & Tìm/Chọn khách hàng -->
        <div class="shrink-0 space-y-1 border-b border-slate-100 bg-white px-2 py-1.5">
            <div class="flex min-w-0 items-center gap-2">
                <div class="min-w-0 flex-1">
                    <CartTabs
                        :tabs="tabs"
                        :active-tab-id="activeTabId"
                        @select="$emit('select-tab', $event)"
                        @create="$emit('create-tab')"
                        @remove="$emit('remove-tab', $event)"
                    />
                </div>
                <button
                    type="button"
                    class="inline-flex shrink-0 items-center gap-1.5 rounded-lg px-2 py-2 text-xs font-bold text-blue-600 transition hover:bg-blue-50 hover:text-blue-700"
                    @click="showSaleHistory = true"
                >
                    <History class="h-3.5 w-3.5" />
                    <span>Lịch sử đơn</span>
                </button>
            </div>
            
            <CustomerSection
                :customer="selectedCustomer"
                @selected="$emit('customer-selected', $event)"
            />
            <p v-if="needsCustomerForWarranty" class="rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-2 text-[11px] text-amber-800">
                Sản phẩm có bảo hành, nên điền thông tin khách hàng để thuận tiện cho việc bảo hành
            </p>
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
        <div class="shrink-0 bg-white px-2 pb-0 pt-1.5">
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <div v-if="cart.length" class="space-y-0.5 border-b border-slate-100 bg-slate-50 px-2.5 py-1.5 text-xs">
                <div class="flex items-center gap-2 text-slate-600">
                    <button
                        type="button"
                        :class="showFeeDiscount ? 'bg-slate-200 text-slate-600 hover:bg-slate-300' : 'bg-blue-100 text-blue-700 hover:bg-blue-200'"
                        class="inline-flex min-w-0 items-center gap-1 rounded-md px-2 py-0.5 text-[11px] font-semibold transition"
                        :aria-expanded="showFeeDiscount"
                        @click="showFeeDiscount = !showFeeDiscount"
                    >
                        <span class="truncate">Phụ phí &amp; chiết khấu</span>
                        <span v-if="!showFeeDiscount && hasFeeOrInvoiceDiscount" class="h-1.5 w-1.5 shrink-0 rounded-full bg-amber-500"></span>
                        <ChevronDown class="h-2.5 w-2.5 shrink-0 transition-transform" :class="showFeeDiscount ? 'rotate-180' : ''" />
                    </button>
                    <span class="whitespace-nowrap">Tạm tính ({{ totalQuantity }} món):</span>
                    <strong class="ml-auto whitespace-nowrap text-slate-800">{{ $money(subTotal) }}</strong>
                </div>
                <div v-if="discount > 0" class="flex items-center justify-between text-rose-600">
                    <span>Chiết khấu sản phẩm:</span><strong>-{{ $money(discount) }}</strong>
                </div>
                <div v-show="showFeeDiscount" class="grid grid-cols-2 gap-2 pt-0.5">
                    <div class="relative min-w-0">
                        <FloatingInput :model-value="extraFeeInputValue" label="Phí phụ thu" :format-thousands="extraFeeType !== 'percent'" :inputmode="extraFeeType === 'percent' ? 'decimal' : 'numeric'" input-class="!h-9 !rounded-md !border-slate-200 !py-2 !pl-2 !pr-11 !text-center !text-xs focus:!border-blue-400" label-class="!left-2 !px-0.5 !text-[10px]" @update:model-value="updateExtraFee" />
                        <button type="button" class="absolute right-0.5 top-0.5 flex h-8 w-8 items-center justify-center rounded-md text-xs font-extrabold text-white shadow-sm" :class="extraFeeType === 'percent' ? 'bg-amber-500 hover:bg-amber-600' : 'bg-blue-600 hover:bg-blue-700'" :aria-label="extraFeeType === 'percent' ? 'Đang tính phí theo phần trăm, nhấn để chuyển sang số tiền' : 'Đang tính phí theo số tiền, nhấn để chuyển sang phần trăm'" @click="toggleExtraFeeType">{{ extraFeeType === 'percent' ? '%' : $currency() }}</button>
                    </div>
                    <div class="relative min-w-0">
                        <FloatingInput :model-value="discountInputValue" label="Chiết khấu đơn" :format-thousands="invoiceDiscountType !== 'percent'" :inputmode="invoiceDiscountType === 'percent' ? 'decimal' : 'numeric'" input-class="!h-9 !rounded-md !border-slate-200 !py-2 !pl-2 !pr-11 !text-center !text-xs focus:!border-blue-400" label-class="!left-2 !px-0.5 !text-[10px]" @update:model-value="updateInvoiceDiscount" />
                        <button type="button" class="absolute right-0.5 top-0.5 flex h-8 w-8 items-center justify-center rounded-md text-xs font-extrabold text-white shadow-sm" :class="invoiceDiscountType === 'percent' ? 'bg-amber-500 hover:bg-amber-600' : 'bg-blue-600 hover:bg-blue-700'" :aria-label="invoiceDiscountType === 'percent' ? 'Đang giảm theo phần trăm, nhấn để chuyển sang số tiền' : 'Đang giảm theo số tiền, nhấn để chuyển sang phần trăm'" @click="toggleInvoiceDiscountType">{{ invoiceDiscountType === 'percent' ? '%' : $currency() }}</button>
                    </div>
                </div>
                <div v-if="vat > 0" class="flex items-center justify-between border-t border-dashed border-slate-200 pt-1 text-emerald-700">
                    <span>Thuế VAT bán hàng:</span><strong>{{ $money(vat) }}</strong>
                </div>
            </div>
            <PaymentPanel
                :cart="cart"
                :grand-total="grandTotal"
                :selected-customer="selectedCustomer"
                :loading="loading"
                @checkout="$emit('checkout', $event)"
            />
            </div>
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
