<script setup>
import { formatDateTime, formatCurrency, formatDate, formatDatePlusDays, formatNumber } from '@/utils/format'
import { usePage } from '@inertiajs/vue3'
import { MessageSquare } from 'lucide-vue-next'
import { ref } from 'vue'

const props = defineProps({
    sale: {
        type: Object,
        required: true
    }
});
const page = usePage()
const openWarrantyNoteId = ref(null)

const toggleWarrantyNote = (item) => {
    openWarrantyNoteId.value = openWarrantyNoteId.value === item.id ? null : item.id
}

const warrantyEndDate = (item) => {
    const days = Number(item.warranty_days ?? item.product_imei?.customer_warranty_days ?? 0)
    const calculatedExpiry = formatDatePlusDays(props.sale?.created_at, days)
    if (calculatedExpiry) return calculatedExpiry

    const expiresAt = item.product_imei?.customer_warranty_expires_at
    return expiresAt ? formatDate(expiresAt) : ''
}

const printInvoice = () => {
    window.print();
};

const attributeText = (attribute) => {
    if (attribute === null || attribute === undefined || attribute === '') {
        return ''
    }

    if (typeof attribute !== 'object') {
        return String(attribute)
    }

    return attribute.value
        ?? attribute.label
        ?? attribute.name
        ?? attribute.title
        ?? attribute.text
        ?? ''
}

const variantLabel = (variant) => {
    const attributes = Object.values(variant?.attributes || {})
        .map(attributeText)
        .filter(Boolean)

    return attributes.join(' / ')
        || variant?.sku
        || ''
}

const saleQuantityText = (item) => {
    const qty = Number(item.quantity ?? 0)
    const unit = item.unit_name || 'Cái'

    return `${Number.isInteger(qty) ? qty : formatNumber(qty)} ${unit}`
}
</script>

<template>
    <div class="max-w-2xl mx-auto p-4 md:p-8 font-sans antialiased text-slate-800">
        <!-- Thanh thao tác trên (Chỉ hiển thị trên màn hình, ẩn khi in) -->
        <div class="flex items-center justify-between mb-6 print:hidden bg-white p-4 rounded-2xl border border-slate-100 shadow-sm">
            <div>
                <h1 class="text-xl font-bold text-slate-900">Chi tiết Hóa đơn</h1>
                <p class="text-xs text-slate-500">Xem trước thông tin trước khi in ấn</p>
            </div>
            
            <button
                @click="printInvoice"
                class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-medium text-sm px-5 py-2.5 rounded-xl transition-all shadow-sm active:scale-95"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                In hóa đơn
            </button>
        </div>

        <!-- Khung Hóa Đơn Chính -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-6 md:p-8 shadow-sm print:border-none print:shadow-none print:p-0">
            
            <!-- Header Cửa hàng -->
            <div class="text-center pb-6 mb-6 border-b border-slate-100">
                <h2 class="text-2xl font-black text-slate-900 tracking-tight mb-1">
                    {{ page.props.settings?.shop_name || 'Cửa hàng' }}
                </h2>
                <p class="text-xs uppercase tracking-widest font-semibold text-slate-400">
                    Hóa đơn bán hàng
                </p>
            </div>

            <!-- Thông tin Đơn hàng -->
            <div class="grid grid-cols-2 gap-4 text-sm mb-6 p-4 rounded-xl bg-slate-50 border border-slate-100/80 print:bg-transparent print:p-0">
                <div>
                    <span class="text-xs text-slate-400 block font-medium">MÃ HÓA ĐƠN</span>
                    <span class="font-bold text-slate-800">#{{ sale.code }}</span>
                </div>
                <div class="text-right">
                    <span class="text-xs text-slate-400 block font-medium">NGÀY TẠO</span>
                    <span class="font-medium text-slate-700">{{ formatDateTime(sale.created_at) }}</span>
                </div>
            </div>

            <!-- Bảng danh sách mặt hàng -->
            <table class="w-full text-sm mb-6">
                <thead>
                    <tr class="border-b border-slate-200 text-slate-400 text-xs uppercase tracking-wider">
                        <th class="text-left py-3 font-semibold">Sản phẩm</th>
                        <th class="text-center py-3 font-semibold w-20">SL</th>
                        <th class="text-right py-3 font-semibold w-28">Thành tiền</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr
                        v-for="item in sale.items"
                        :key="item.id"
                        class="hover:bg-slate-50/50 transition-colors"
                    >
                        <td class="py-3 pr-2">
                            <div class="font-medium text-slate-800">{{ item.product?.name }}</div>
                            
                            <div
                                v-if="item.variant?.attributes"
                                class="inline-block mt-0.5 text-xs text-blue-600 font-medium bg-blue-50 px-1.5 py-0.5 rounded"
                            >
                                {{ variantLabel(item.variant) }}
                            </div>

                            <div
                                v-if="item.product_imei"
                                class="text-xs text-slate-500 font-mono mt-0.5"
                            >
                                IMEI: {{ item.product_imei.imei }}
                            </div>
                            <div v-if="item.product_imei?.customer_warranty_days !== null && item.product_imei?.customer_warranty_days !== undefined" class="mt-0.5 inline-flex items-center gap-1.5">
                                <span class="inline-flex items-center rounded bg-emerald-50 px-1.5 py-0.5 text-xs font-semibold" :class="warrantyEndDate(item) ? 'text-emerald-700' : 'text-slate-500'">
                                {{ warrantyEndDate(item)
                                    ? `Bảo hành đến ${warrantyEndDate(item)}`
                                    : 'Không áp dụng bảo hành' }}
                                </span>
                                <button v-if="item.warranty_note" type="button" class="inline-flex h-6 w-6 items-center justify-center rounded text-slate-500 hover:bg-amber-50 hover:text-amber-700 print:hidden" :aria-label="`Xem ghi chú bảo hành ${item.product?.name || ''}`" :aria-expanded="openWarrantyNoteId === item.id" @click="toggleWarrantyNote(item)">
                                    <MessageSquare :size="14" />
                                </button>
                            </div>
                            <div v-else-if="(item.warranty_days !== null && item.warranty_days !== undefined) || item.warranty_note" class="mt-0.5 inline-flex items-center gap-1.5">
                                <span v-if="item.warranty_days !== null && item.warranty_days !== undefined" class="inline-flex items-center rounded bg-emerald-50 px-1.5 py-0.5 text-xs font-semibold" :class="Number(item.warranty_days) > 0 ? 'text-emerald-700' : 'text-slate-500'">
                                    {{ Number(item.warranty_days) > 0 && warrantyEndDate(item) ? `Bảo hành đến ${warrantyEndDate(item)}` : 'Không áp dụng bảo hành' }}
                                </span>
                                <button v-if="item.warranty_note" type="button" class="inline-flex h-6 w-6 items-center justify-center rounded text-slate-500 hover:bg-amber-50 hover:text-amber-700 print:hidden" :aria-label="`Xem ghi chú bảo hành ${item.product?.name || ''}`" :aria-expanded="openWarrantyNoteId === item.id" @click="toggleWarrantyNote(item)">
                                    <MessageSquare :size="14" />
                                </button>
                            </div>
                            <div v-if="item.warranty_note && openWarrantyNoteId === item.id" class="mt-1 max-w-sm rounded border border-amber-200 bg-amber-50 px-2 py-1.5 text-xs font-medium text-amber-900 whitespace-pre-line print:hidden">
                                {{ item.warranty_note }}
                            </div>
                        </td>

                        <td class="text-center py-3 text-slate-600 font-medium">
                            {{ saleQuantityText(item) }}
                        </td>

                        <td class="text-right py-3 font-semibold text-slate-800">
                            {{ formatCurrency(item.subtotal ?? (item.unit_price * item.quantity)) }}
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Tổng kết tiền thanh toán -->
            <div class="space-y-2.5 pt-4 border-t border-slate-200 text-sm">
                <div class="flex justify-between text-slate-600">
                    <span>Tổng tiền hàng</span>
                    <span class="font-semibold text-slate-800">
                        {{ formatCurrency(sale.grand_total) }}
                    </span>
                </div>

                <div class="flex justify-between text-slate-600">
                    <span>Khách thanh toán</span>
                    <span class="font-semibold text-slate-800">
                        {{ formatCurrency(sale.paid_amount) }}
                    </span>
                </div>

                <div v-if="Number(sale.cash_received_amount || 0) > 0 && Number(sale.bank_received_amount || 0) > 0" class="space-y-1 border-t border-dashed border-slate-200 pt-2 text-xs text-slate-600">
                    <div class="flex justify-between"><span>Tiền mặt</span><span>{{ formatCurrency(sale.cash_received_amount) }}</span></div>
                    <div class="flex justify-between"><span>Chuyển khoản{{ sale.bank_account ? ` · ${sale.bank_account}` : '' }}</span><span>{{ formatCurrency(sale.bank_received_amount) }}</span></div>
                </div>

                <div class="flex justify-between items-center pt-2 border-t border-dashed border-slate-200 text-base font-bold text-slate-900">
                    <span>Tiền thừa trả khách</span>
                    <span class="text-blue-600 text-lg">
                        {{ formatCurrency(sale.change_amount) }}
                    </span>
                </div>
            </div>

            <!-- Footer cảm ơn -->
            <div class="text-center mt-8 pt-6 border-t border-slate-100 text-xs text-slate-400">
                <p class="font-medium">Cảm ơn quý khách và hẹn gặp lại!</p>
            </div>
        </div>
    </div>
</template>
