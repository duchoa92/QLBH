<!-- resources/js/Components/InvoiceDetailModal.vue -->
<script setup>
import { computed } from 'vue'
import {
    CheckCircle2,
    Gift,
    Printer,
    Undo2,
    XCircle,
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

const money = (value) => Number(value || 0).toLocaleString('vi-VN')

const formatDate = (date) => {
    if (!date) return '-'

    return new Date(date).toLocaleString('vi-VN')
}

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

const saleQuantityText = (item) => {
    const qty = Number(item.quantity ?? 0)
    const unit = item.unit_name || 'Cái'

    return `${Number.isInteger(qty) ? qty : qty.toLocaleString('vi-VN')} ${unit}`
}

const baseQuantityText = (item) => {
    if (Number(item.conversion_factor || 1) <= 1) return ''

    const unit = item.product?.unit?.short_name
        || item.product?.unit?.name
        || 'đơn vị gốc'

    return ` = ${item.base_quantity} ${unit}`
}

const saleTotal = computed(() =>
    props.invoice?.grand_total
    ?? props.invoice?.total_amount
    ?? props.invoice?.subtotal
    ?? 0
)

const customerName = computed(() =>
    props.invoice?.customer?.full_name
    || props.invoice?.customer?.name
    || 'Khách lẻ'
)

const isCancelled = computed(() => props.invoice?.status === 'cancelled')

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

const balanceText = computed(() => {
    if (!props.invoice || isCancelled.value) return null

    const total = Number(saleTotal.value || 0)
    const paid = Number(props.invoice.paid_amount || 0)
    const balance = paid - total

    if (balance < 0) {
        return {
            label: 'Thiếu',
            value: Math.abs(balance),
            class: 'text-rose-600',
        }
    }

    if (balance > 0) {
        return {
            label: 'Thừa',
            value: balance,
            class: 'text-emerald-600',
        }
    }

    return {
        label: 'Đủ',
        value: 0,
        class: 'text-slate-600',
    }
})

const cancelReason = computed(() =>
    props.invoice?.cancel_reason
    || props.invoice?.reason
    || props.invoice?.cancellation_reason
    || 'Chưa ghi nhận lý do'
)
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/55 p-3 sm:p-4"
        @click.self="emit('close')"
    >
        <div class="flex max-h-[92vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
            <div class="flex items-start justify-between gap-4 border-b border-slate-200 bg-white px-4 py-3 sm:px-5">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-base font-bold text-slate-900 sm:text-lg">
                            Chi tiết hóa đơn
                        </h2>
                        <span
                            v-if="invoice?.code"
                            class="rounded-lg border border-indigo-100 bg-indigo-50 px-2 py-0.5 text-xs font-bold text-indigo-700"
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
                    <p v-if="invoice" class="mt-1 text-xs text-slate-500">
                        {{ customerName }} • {{ formatDate(invoice.created_at) }}
                    </p>
                </div>

                <button
                    type="button"
                    class="rounded-lg px-2 text-2xl font-bold leading-none text-slate-400 transition hover:bg-slate-100 hover:text-rose-500"
                    @click="emit('close')"
                >
                    &times;
                </button>
            </div>

            <div v-if="invoice" class="flex-1 overflow-y-auto p-4 sm:p-5">
                <div
                    v-if="isCancelled"
                    class="mb-4 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800"
                >
                    <div class="flex items-center gap-2 font-bold text-rose-700">
                        <XCircle :size="18" />
                        <span>Hóa đơn đã hủy {{ invoice.cancelled_at ? `lúc ${formatDate(invoice.cancelled_at)}` : '' }}</span>
                    </div>
                    <div class="mt-1 pl-7 text-xs text-rose-800">
                        Lý do hủy: <span class="font-semibold italic">{{ cancelReason }}</span>
                    </div>
                    <div class="mt-1 pl-7 text-xs text-rose-600">
                        Toàn bộ sản phẩm và IMEI thuộc hóa đơn này đã được hoàn trả về kho.
                    </div>
                </div>

                <div class="grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                        <div class="text-[11px] font-bold uppercase text-slate-400">Mã hóa đơn</div>
                        <div class="mt-1 font-bold text-slate-900">#{{ invoice.code }}</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                        <div class="text-[11px] font-bold uppercase text-slate-400">Khách hàng</div>
                        <div class="mt-1 truncate font-semibold text-slate-900">{{ customerName }}</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                        <div class="text-[11px] font-bold uppercase text-slate-400">Thu ngân</div>
                        <div class="mt-1 truncate font-medium text-slate-800">{{ invoice.user?.name || '-' }}</div>
                    </div>
                    <div class="rounded-2xl border border-indigo-200 bg-indigo-50 p-3">
                        <div class="text-[11px] font-bold uppercase text-indigo-500">Tổng thanh toán</div>
                        <div class="mt-1 font-black text-indigo-700">{{ money(saleTotal) }} đ</div>
                    </div>
                </div>

                <div class="mt-4 overflow-x-auto rounded-2xl border border-slate-200">
                    <table class="w-full min-w-[760px] text-left text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50 text-xs font-bold uppercase text-slate-500">
                                <th class="w-12 px-4 py-3 text-center">STT</th>
                                <th class="px-4 py-3">Sản phẩm</th>
                                <th class="w-28 px-4 py-3 text-center">Loại</th>
                                <th class="w-28 px-4 py-3 text-center">SL</th>
                                <th class="w-32 px-4 py-3 text-right">Đơn giá</th>
                                <th class="w-32 px-4 py-3 text-right">Thành tiền</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <template
                                v-for="(item, index) in invoice.items || []"
                                :key="item.id || index"
                            >
                                <tr class="align-top hover:bg-slate-50/70">
                                    <td class="px-4 py-3 text-center text-slate-400">{{ index + 1 }}</td>
                                    <td class="px-4 py-3">
                                        <div class="font-semibold text-slate-900">
                                            {{ item.product?.name || item.product_name || '-' }}
                                        </div>
                                        <div
                                            v-if="variantLabel(item.variant)"
                                            class="mt-1 inline-flex rounded-lg bg-indigo-50 px-2 py-0.5 text-xs font-semibold text-indigo-700"
                                        >
                                            {{ variantLabel(item.variant) }}
                                        </div>
                                        <div
                                            v-if="item.product_imei?.imei || item.imei"
                                            class="mt-1 font-mono text-xs font-semibold text-blue-600"
                                        >
                                            IMEI: {{ item.product_imei?.imei || item.imei }}
                                        </div>
                                        <div
                                            v-if="Number(item.discount_value || 0) > 0"
                                            class="mt-1 text-xs font-medium text-rose-600"
                                        >
                                            <template v-if="item.discount_type === 'percent'">
                                                Giảm {{ item.discount_value }}%
                                            </template>
                                            <template v-else>
                                                Giảm {{ money(item.discount_value) }} đ
                                            </template>
                                        </div>
                                        <div v-if="item.note" class="mt-1 text-xs italic text-slate-400">
                                            Ghi chú: {{ item.note }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="rounded-full border border-blue-100 bg-blue-50 px-2 py-0.5 text-xs font-bold text-blue-700">
                                            Sản phẩm
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center font-semibold text-slate-800">
                                        <div>{{ saleQuantityText(item) }}</div>
                                        <div
                                            v-if="baseQuantityText(item)"
                                            class="mt-0.5 text-[11px] font-medium text-indigo-600"
                                        >
                                            {{ baseQuantityText(item) }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-right font-medium text-slate-700">
                                        {{ money(item.unit_price) }} đ
                                    </td>
                                    <td class="px-4 py-3 text-right font-bold text-slate-900">
                                        {{ money(item.subtotal) }} đ
                                    </td>
                                </tr>

                                <tr
                                    v-for="(gift, giftIndex) in item.gifts || []"
                                    :key="`gift-${item.id || index}-${gift.id || giftIndex}`"
                                    class="bg-emerald-50/35 text-sm"
                                >
                                    <td class="px-4 py-2 text-center text-slate-300">-</td>
                                    <td class="px-4 py-2 pl-8 text-emerald-800">
                                        <span class="inline-flex items-center gap-1 font-semibold">
                                            <Gift :size="13" />
                                            {{ gift.product?.name || gift.product_name || 'Quà tặng' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-center">
                                        <span class="rounded-full border border-emerald-100 bg-emerald-50 px-2 py-0.5 text-xs font-bold text-emerald-700">
                                            Quà tặng
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-center font-medium text-slate-700">
                                        {{ gift.quantity || 1 }}
                                    </td>
                                    <td class="px-4 py-2 text-right text-xs text-slate-400 line-through">
                                        {{ money(gift.product?.retail_price || gift.product?.sell_price || gift.product?.price) }} đ
                                    </td>
                                    <td class="px-4 py-2 text-right font-bold text-emerald-600">0 đ</td>
                                </tr>

                                <tr v-if="item.gift_product" class="bg-emerald-50/35 text-sm">
                                    <td class="px-4 py-2 text-center text-slate-300">-</td>
                                    <td class="px-4 py-2 pl-8 text-emerald-800">
                                        <span class="inline-flex items-center gap-1 font-semibold">
                                            <Gift :size="13" />
                                            {{ item.gift_product.name }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-center">
                                        <span class="rounded-full border border-emerald-100 bg-emerald-50 px-2 py-0.5 text-xs font-bold text-emerald-700">
                                            Quà tặng
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-center font-medium text-slate-700">1</td>
                                    <td class="px-4 py-2 text-right text-xs text-slate-400 line-through">
                                        {{ money(item.gift_product.retail_price || item.gift_product.sell_price || item.gift_product.price) }} đ
                                    </td>
                                    <td class="px-4 py-2 text-right font-bold text-emerald-600">0 đ</td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 grid gap-4 lg:grid-cols-[1fr_360px]">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-xs text-slate-500">
                        <div class="font-bold uppercase text-slate-400">Ghi chú</div>
                        <div class="mt-1 text-slate-700">
                            {{ invoice.note || 'Không có ghi chú.' }}
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 p-4 text-sm">
                        <div class="space-y-2">
                            <div class="flex justify-between gap-4">
                                <span class="text-slate-500">Tiền hàng</span>
                                <span class="font-semibold text-slate-900">{{ money(invoice.subtotal) }} đ</span>
                            </div>
                            <div
                                v-if="Number(invoice.discount || 0) > 0"
                                class="flex justify-between gap-4 text-rose-600"
                            >
                                <span>Giảm giá hóa đơn</span>
                                <span class="font-semibold">-{{ money(invoice.discount) }} đ</span>
                            </div>
                            <div class="flex justify-between gap-4 border-t border-dashed border-slate-200 pt-2 text-base font-black text-indigo-700">
                                <span>Tổng thanh toán</span>
                                <span>{{ money(saleTotal) }} đ</span>
                            </div>
                            <div class="flex justify-between gap-4 pt-1 text-slate-600">
                                <span>Khách trả</span>
                                <span class="font-semibold">{{ money(invoice.paid_amount) }} đ</span>
                            </div>
                            <div
                                v-if="balanceText"
                                class="flex justify-between gap-4 font-bold"
                                :class="balanceText.class"
                            >
                                <span>{{ balanceText.label }}</span>
                                <span>{{ money(balanceText.value) }} đ</span>
                            </div>
                            <div class="flex justify-between gap-4 pt-1 text-xs uppercase text-slate-400">
                                <span>Thanh toán</span>
                                <span class="font-bold text-slate-600">{{ invoice.payment_method || '-' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-col-reverse gap-2 border-t border-slate-200 bg-slate-50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                <button
                    v-if="canCancel && invoice && !isCancelled"
                    type="button"
                    class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-rose-50 px-4 py-2 text-xs font-bold text-rose-600 transition hover:bg-rose-100"
                    @click="emit('cancel', invoice)"
                >
                    <Undo2 :size="14" />
                    Hủy hóa đơn này
                </button>
                <div v-else></div>

                <div class="flex items-center justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-600 transition hover:bg-slate-100"
                        @click="emit('close')"
                    >
                        Đóng
                    </button>
                    <button
                        v-if="canPrint && invoice && !isCancelled"
                        type="button"
                        class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-indigo-700"
                        @click="emit('print', invoice)"
                    >
                        <Printer :size="14" />
                        In hóa đơn
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
