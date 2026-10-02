<!-- resources/js/Components/InvoiceDetailModal.vue -->
<script setup>
import { computed } from 'vue'
import {
    CheckCircle2,
    Gift,
    Printer,
    Undo2,
    XCircle,
    X,
    FileText,
    User,
    UserCheck,
    CreditCard,
    Hash,
    AlertTriangle,
    Tag,
    MessageSquare
} from 'lucide-vue-next'

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    invoice: {
        type: Object,
        default: null,
    },
    canCancel: {
        type: Boolean,
        default: false,
    },
    canPrint: {
        type: Boolean,
        default: false,
    },
})

const emit = defineEmits(['close', 'cancel', 'print'])

// Format tiền tệ
const money = (value) => Number(value || 0).toLocaleString('vi-VN')

// Format ngày tháng
const formatDate = (date) => {
    if (!date) return '-'
    return new Date(date).toLocaleString('vi-VN')
}

// Lấy thuộc tính biến thể
const attributeText = (attribute) => {
    if (attribute === null || attribute === undefined || attribute === '') return ''
    if (typeof attribute !== 'object') return String(attribute)

    return attribute.value
        || attribute.label
        || attribute.name
        || attribute.title
        || attribute.text
        || ''
}

const variantLabel = (variant) => {
    const attributes = Object.values(variant?.attributes || {})
        .map(attributeText)
        .filter(Boolean)

    return attributes.join(' / ') || variant?.name || variant?.sku || ''
}

// Đơn vị & Số lượng bán
const saleQuantityText = (item) => {
    const qty = Number(item.quantity ?? 0)
    const unit = item.unit_name || 'Chiếc'

    return `${Number.isInteger(qty) ? qty : qty.toLocaleString('vi-VN')} ${unit}`
}

// Quy đổi đơn vị gốc
const baseQuantityText = (item) => {
    if (Number(item.conversion_factor || 1) <= 1) return ''

    const unit = item.product?.unit?.short_name
        || item.product?.unit?.name
        || 'đơn vị gốc'

    return ` = ${item.base_quantity} ${unit}`
}

// Tổng tiền hóa đơn
const saleTotal = computed(() =>
    props.invoice?.grand_total
    ?? props.invoice?.total_amount
    ?? props.invoice?.subtotal
    ?? 0
)

// Tên khách hàng
const customerName = computed(() =>
    props.invoice?.customer?.full_name
    || props.invoice?.customer?.name
    || 'Khách lẻ'
)

const isCancelled = computed(() => props.invoice?.status === 'cancelled')

// Trạng thái thanh toán / Hủy
const paymentStatusMeta = computed(() => {
    if (isCancelled.value) {
        return {
            label: 'Đã hủy',
            icon: XCircle,
            class: 'border-rose-200 bg-rose-50 text-rose-700',
        }
    }

    return {
        label: 'Hoàn thành',
        icon: CheckCircle2,
        class: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    }
})

// Tiền thừa / thiếu
const balanceText = computed(() => {
    if (!props.invoice || isCancelled.value) return null

    const total = Number(saleTotal.value || 0)
    const paid = Number(props.invoice.paid_amount || 0)
    const balance = paid - total

    if (balance < 0) {
        return {
            label: 'Còn thiếu',
            value: Math.abs(balance),
            class: 'text-rose-600',
        }
    }

    if (balance > 0) {
        return {
            label: 'Tiền thừa',
            value: balance,
            class: 'text-emerald-600',
        }
    }

    return {
        label: 'Thanh toán đủ',
        value: 0,
        class: 'text-slate-600',
    }
})

// Lý do hủy
const cancelReason = computed(() =>
    props.invoice?.cancel_reason
    || props.invoice?.reason
    || props.invoice?.cancellation_reason
    || 'Chưa ghi nhận lý do'
)
</script>

<template>
    <Teleport to="body">
    <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-2 sm:p-3 backdrop-blur-xs transition-opacity"
        @click.self="emit('close')"
    >
        <!-- NỀN MODAL DÙNG BG-SLATE-50 ĐỒNG BỘ VỚI HEADER VÀ DÙNG RING BẢO VỆ MÉP -->
        <div class="flex max-h-[92vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl bg-slate-50 shadow-2xl ring-1 ring-black/5">
            
            <!-- 1. HEADER CỐ ĐỊNH (MÀU NỀN ĐẶC BG-SLATE-50 VÀ KHÔNG BỊ HỞ VIỀN SÁNG) -->
            <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-4 py-3 shrink-0">
                <div class="flex items-center gap-2.5">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 font-bold shrink-0">
                        <FileText :size="18" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-base font-extrabold text-slate-900">Chi tiết hóa đơn</h3>
                            <span
                                v-if="invoice?.code"
                                class="rounded-md border border-indigo-200 bg-indigo-50 px-2 py-0.5 text-xs font-bold text-indigo-700"
                            >
                                #{{ invoice.code }}
                            </span>
                            <span
                                v-if="invoice"
                                class="inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-bold"
                                :class="paymentStatusMeta.class"
                            >
                                <component :is="paymentStatusMeta.icon" :size="13" />
                                {{ paymentStatusMeta.label }}
                            </span>
                        </div>
                        <p v-if="invoice" class="mt-0.5 text-xs font-medium text-slate-500">
                            {{ customerName }} • {{ formatDate(invoice.created_at) }}
                        </p>
                    </div>
                </div>

                <button
                    type="button"
                    class="rounded-lg p-1 text-slate-400 transition hover:bg-slate-200 hover:text-slate-700"
                    @click="emit('close')"
                >
                    <X :size="20" />
                </button>
            </div>

            <!-- 2. BODY SCROLLABLE (MÀU NỀN TRẮNG BG-WHITE BÊN TRONG) -->
            <div v-if="invoice" class="flex-1 overflow-y-auto bg-white p-3.5 space-y-3">
                
                <!-- KHỐI THÔNG BÁO HỦY ĐƠN -->
                <div
                    v-if="isCancelled"
                    class="flex items-start gap-2.5 rounded-xl border border-rose-200 bg-rose-50/90 p-3 text-xs text-rose-800"
                >
                    <AlertTriangle :size="18" class="text-rose-600 shrink-0 mt-0.5" />
                    <div class="space-y-0.5">
                        <div class="font-bold text-sm">
                            Hóa đơn hủy do: <span class="italic font-semibold text-xs text-rose-700">{{ cancelReason }}</span>. {{ invoice.cancelled_at ? `lúc ${formatDate(invoice.cancelled_at)}` : '' }}
                        </div>
                        <div class="text-[11px] text-rose-500 italic">
                            * Toàn bộ sản phẩm và mã IMEI/Serial thuộc hóa đơn này đã được hoàn trả về kho.
                        </div>
                    </div>
                </div>

                <!-- THÔNG TIN CHUNG -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                    <div class="rounded-xl border border-slate-200/80 bg-slate-50/60 p-2.5">
                        <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 flex items-center gap-1">
                            <Hash :size="12" /> Mã hóa đơn
                        </div>
                        <div class="mt-0.5 font-bold text-slate-900 text-sm">#{{ invoice.code }}</div>
                    </div>

                    <div class="rounded-xl border border-slate-200/80 bg-slate-50/60 p-2.5">
                        <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 flex items-center gap-1">
                            <User :size="12" /> Khách hàng
                        </div>
                        <div class="mt-0.5 font-bold text-slate-900 text-sm truncate">{{ customerName }}</div>
                        <div v-if="invoice.customer?.phone" class="text-xs text-slate-400 font-medium">
                            {{ invoice.customer.phone }}
                        </div>
                    </div>

                    <div class="rounded-xl border border-slate-200/80 bg-slate-50/60 p-2.5">
                        <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 flex items-center gap-1">
                            <UserCheck :size="12" /> Thu ngân
                        </div>
                        <div class="mt-0.5 font-bold text-slate-900 text-sm truncate">
                            {{ invoice.user?.name || '-' }}
                        </div>
                    </div>

                    <div class="rounded-xl border border-indigo-100 bg-indigo-50/60 p-2.5">
                        <div class="text-[10px] font-extrabold uppercase tracking-wider text-indigo-500 flex items-center gap-1">
                            <CreditCard :size="12" /> Tổng thanh toán
                        </div>
                        <div class="mt-0.5 font-black text-indigo-700 text-base">
                            {{ money(saleTotal) }} đ
                        </div>
                    </div>
                </div>

                <!-- BẢNG SẢN PHẨM -->
                <div class="overflow-hidden rounded-xl border border-slate-200/80 shadow-2xs bg-white">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[700px] text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-slate-50 text-[11px] font-extrabold uppercase tracking-wider text-slate-500 border-b border-slate-200">
                                    <th class="w-10 px-3 py-2 text-center">STT</th>
                                    <th class="px-3 py-2">Sản phẩm</th>
                                    <th class="w-28 min-w-[100px] px-3 py-2 text-center whitespace-nowrap">Loại</th>
                                    <th class="w-24 px-3 py-2 text-center whitespace-nowrap">SL</th>
                                    <th class="w-28 px-3 py-2 text-right whitespace-nowrap">Đơn giá</th>
                                    <th class="w-32 px-3 py-2 text-right whitespace-nowrap">Thành tiền</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 font-medium text-xs">
                                <template
                                    v-for="(item, index) in invoice.items || []"
                                    :key="item.id || index"
                                >
                                    <tr class="hover:bg-slate-50/60 transition">
                                        <td class="px-3 py-2.5 text-center text-slate-400 font-bold">
                                            {{ index + 1 }}
                                        </td>
                                        <td class="px-3 py-2.5">
                                            <div class="font-bold text-slate-900 text-sm">
                                                {{ item.product?.name || item.product_name || '-' }}
                                            </div>

                                            <div
                                                v-if="variantLabel(item.variant)"
                                                class="mt-1 inline-flex items-center rounded-md bg-indigo-50 px-2 py-0.5 text-xs font-semibold text-indigo-700"
                                            >
                                                {{ variantLabel(item.variant) }}
                                            </div>

                                            <div v-if="item.product_imei?.imei || item.imei" class="mt-1">
                                                <span class="inline-flex items-center gap-1 rounded-md bg-blue-50 px-2 py-0.5 text-xs font-mono font-bold text-blue-600 border border-blue-100">
                                                    <Tag :size="11" /> IMEI: {{ item.product_imei?.imei || item.imei }}
                                                </span>
                                            </div>

                                            <div
                                                v-if="Number(item.discount_value || 0) > 0"
                                                class="mt-1 text-xs font-bold text-rose-600"
                                            >
                                                <template v-if="item.discount_type === 'percent'">
                                                    (Giảm {{ item.discount_value }}%)
                                                </template>
                                                <template v-else>
                                                    (Giảm {{ money(item.discount_value) }} đ)
                                                </template>
                                            </div>

                                            <div v-if="item.note" class="mt-1 text-xs italic text-slate-400">
                                                Ghi chú: {{ item.note }}
                                            </div>
                                        </td>

                                        <td class="px-3 py-2.5 text-center whitespace-nowrap">
                                            <span class="inline-block rounded-full border border-blue-100 bg-blue-50 px-2.5 py-0.5 text-xs font-bold text-blue-600 whitespace-nowrap">
                                                Sản phẩm
                                            </span>
                                        </td>

                                        <td class="px-3 py-2.5 text-center font-bold text-slate-800 text-xs whitespace-nowrap">
                                            <div>{{ saleQuantityText(item) }}</div>
                                            <div
                                                v-if="baseQuantityText(item)"
                                                class="mt-0.5 text-[11px] font-semibold text-indigo-600"
                                            >
                                                {{ baseQuantityText(item) }}
                                            </div>
                                        </td>

                                        <td class="px-3 py-2.5 text-right font-medium text-slate-700 text-xs whitespace-nowrap">
                                            {{ money(item.unit_price) }} đ
                                        </td>

                                        <td class="px-3 py-2.5 text-right font-extrabold text-slate-900 text-sm whitespace-nowrap">
                                            {{ money(item.subtotal) }} đ
                                        </td>
                                    </tr>

                                    <!-- QUÀ TẶNG -->
                                    <tr
                                        v-for="(gift, giftIndex) in item.gifts || []"
                                        :key="`gift-${item.id || index}-${gift.id || giftIndex}`"
                                        class="bg-emerald-50/40 text-xs"
                                    >
                                        <td class="px-3 py-2 text-center text-slate-300">-</td>
                                        <td class="px-3 py-2 text-emerald-800">
                                            <span class="inline-flex items-center gap-1 font-bold">
                                                <Gift :size="13" class="text-emerald-600" />
                                                {{ gift.product?.name || gift.product_name || 'Quà tặng' }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 text-center whitespace-nowrap">
                                            <span class="inline-block rounded-full border border-emerald-200 bg-emerald-100/60 px-2.5 py-0.5 text-xs font-bold text-emerald-700 whitespace-nowrap">
                                                Quà tặng
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 text-center font-bold text-slate-700">
                                            {{ gift.quantity || 1 }}
                                        </td>
                                        <td class="px-3 py-2 text-right text-xs text-slate-400 line-through whitespace-nowrap">
                                            {{ money(gift.product?.retail_price || gift.product?.sell_price || gift.product?.price) }} đ
                                        </td>
                                        <td class="px-3 py-2 text-right font-bold text-emerald-600 whitespace-nowrap">0 đ</td>
                                    </tr>

                                    <tr v-if="item.gift_product" class="bg-emerald-50/40 text-xs">
                                        <td class="px-3 py-2 text-center text-slate-300">-</td>
                                        <td class="px-3 py-2 text-emerald-800">
                                            <span class="inline-flex items-center gap-1 font-bold">
                                                <Gift :size="13" class="text-emerald-600" />
                                                {{ item.gift_product.name }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 text-center whitespace-nowrap">
                                            <span class="inline-block rounded-full border border-emerald-200 bg-emerald-100/60 px-2.5 py-0.5 text-xs font-bold text-emerald-700 whitespace-nowrap">
                                                Quà tặng
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 text-center font-bold text-slate-700">1</td>
                                        <td class="px-3 py-2 text-right text-xs text-slate-400 line-through whitespace-nowrap">
                                            {{ money(item.gift_product.retail_price || item.gift_product.sell_price || item.gift_product.price) }} đ
                                        </td>
                                        <td class="px-3 py-2 text-right font-bold text-emerald-600 whitespace-nowrap">0 đ</td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- TỔNG TÍNH TIỀN & GHI CHÚ -->
                <div class="grid gap-2.5 lg:grid-cols-[1fr_320px]">
                    <div class="rounded-xl border border-slate-200/80 bg-slate-50/50 p-2.5 text-xs">
                        <div class="font-extrabold uppercase text-[10px] tracking-wider text-slate-400 flex items-center gap-1 mb-1">
                            <MessageSquare :size="12" /> Ghi chú đơn hàng
                        </div>
                        <div class="text-slate-700 font-medium italic text-xs">
                            {{ invoice.note || 'Không có ghi chú.' }}
                        </div>
                    </div>

                    <div class="rounded-xl border border-slate-200/80 p-3 text-xs space-y-1.5 bg-white">
                        <div class="flex justify-between text-slate-500">
                            <span>Tiền hàng:</span>
                            <span class="font-semibold text-slate-800 text-xs">{{ money(invoice.subtotal) }} đ</span>
                        </div>

                        <div
                            v-if="Number(invoice.discount || 0) > 0"
                            class="flex justify-between text-rose-600 font-medium"
                        >
                            <span>Giảm giá hóa đơn:</span>
                            <span>-{{ money(invoice.discount) }} đ</span>
                        </div>

                        <div class="flex justify-between items-center border-t border-slate-200/80 pt-1.5 text-sm">
                            <span class="font-bold text-slate-900">Tổng thanh toán:</span>
                            <span class="font-black text-indigo-600 text-base">{{ money(saleTotal) }} đ</span>
                        </div>

                        <div class="flex justify-between text-slate-600 border-t border-dashed border-slate-100 pt-1">
                            <span>Khách đã trả:</span>
                            <span class="font-bold text-slate-800">{{ money(invoice.paid_amount) }} đ</span>
                        </div>

                        <div
                            v-if="balanceText"
                            class="flex justify-between font-bold pt-0.5"
                            :class="balanceText.class"
                        >
                            <span>{{ balanceText.label }}:</span>
                            <span>{{ money(balanceText.value) }} đ</span>
                        </div>

                        <div class="flex justify-between text-[11px] uppercase text-slate-400 pt-1 border-t border-slate-100">
                            <span>Phương thức:</span>
                            <span class="font-bold text-slate-700">{{ invoice.payment_method || 'Tiền mặt' }}</span>
                        </div>
                    </div>
                </div>

            </div>

            <!-- 3. FOOTER CỐ ĐỊNH (MÀU NỀN ĐẶC BG-SLATE-50) -->
            <div class="flex items-center justify-between border-t border-slate-200 bg-slate-50 px-4 py-2.5 shrink-0">
                <div>
                    <button
                        v-if="canCancel && invoice && !isCancelled"
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-rose-200 bg-white px-3.5 py-1.5 text-xs font-bold text-rose-600 transition hover:bg-rose-50 active:scale-95"
                        @click="emit('cancel', invoice)"
                    >
                        <Undo2 :size="14" />
                        Hủy hóa đơn
                    </button>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="rounded-xl border border-slate-200 bg-white px-4 py-1.5 text-xs font-bold text-slate-700 transition hover:bg-slate-100"
                        @click="emit('close')"
                    >
                        Đóng
                    </button>
                    
                    <button
                        v-if="canPrint && invoice && !isCancelled"
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-4 py-1.5 text-xs font-bold text-white shadow-xs transition hover:bg-indigo-700 active:scale-95"
                        @click="emit('print', invoice)"
                    >
                        <Printer :size="14" />
                        In hóa đơn
                    </button>
                </div>
            </div>

        </div>
    </div>
    </Teleport>
</template>
