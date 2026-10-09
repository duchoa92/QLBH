<script setup>
import { formatDateTime, formatMoney, formatNumber } from '@/utils/format'
import { Printer, ArrowLeft, Tag, Calendar, User, DollarSign } from 'lucide-vue-next'
import { Link, usePage } from '@inertiajs/vue3'

const props = defineProps({
    sale: {
        type: Object,
        required: true
    }
})
const page = usePage()

const saleQuantityText = (item) => {
    const qty = Number(item.quantity ?? 0)
    const unit = item.unit_name || 'Cái'
    return `${Number.isInteger(qty) ? qty : formatNumber(qty)} ${unit}`
}

const baseQuantityText = (item) => {
    if (Number(item.conversion_factor || 1) <= 1) return ''

    const unit = item.product?.unit?.short_name
        || item.product?.unit?.name
        || 'đơn vị gốc'

    return `= ${item.base_quantity} ${unit}`
}

const printInvoice = () => {
    window.print()
}
</script>

<template>
    <div class="max-w-3xl mx-auto p-4 md:p-8 font-sans text-slate-800">
        <!-- Thanh điều hướng & Thao tác (Ẩn khi in) -->
        <div class="flex items-center justify-between mb-6 print:hidden bg-white p-4 rounded-2xl border border-slate-100 shadow-sm">
            <Link
                :href="route('sales.index')"
                class="inline-flex items-center gap-2 text-xs font-semibold text-slate-600 hover:text-slate-900 transition"
            >
                <ArrowLeft :size="16" />
                 Quay lại danh sách
            </Link>

            <button
                @click="printInvoice"
                class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs px-4 py-2.5 rounded-xl transition shadow-sm"
            >
                <Printer :size="15" />
                In hóa đơn
            </button>
        </div>

        <!-- Khung Hóa Đơn Chi Tiết -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-6 md:p-8 shadow-sm print:border-none print:shadow-none print:p-0">
            <!-- Header Cửa hàng -->
            <div class="text-center pb-6 mb-6 border-b border-slate-100">
                <h2 class="text-2xl font-black text-slate-900 tracking-tight mb-1">
                    {{ page.props.settings?.shop_name || 'Cửa hàng' }}
                </h2>
                <p class="text-xs uppercase tracking-widest font-bold text-indigo-600">
                    Chi tiết hóa đơn bán hàng
                </p>
            </div>

            <!-- Tóm tắt Thông tin Đơn hàng -->
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm mb-6 p-4 rounded-xl bg-slate-50 border border-slate-100">
                <div>
                    <span class="text-[11px] font-bold text-slate-400 block uppercase flex items-center gap-1">
                        <Tag :size="11" /> Mã Hóa Đơn
                    </span>
                    <span class="font-bold text-slate-900 text-base">#{{ sale.code }}</span>
                </div>
                <div>
                    <span class="text-[11px] font-bold text-slate-400 block uppercase flex items-center gap-1">
                        <Calendar :size="11" /> Ngày Tạo
                    </span>
                    <span class="font-semibold text-slate-700 text-xs mt-1 block">
                        {{ formatDateTime(sale.created_at) }}
                    </span>
                </div>
                <div v-if="sale.customer">
                    <span class="text-[11px] font-bold text-slate-400 block uppercase flex items-center gap-1">
                        <User :size="11" /> Khách Hàng
                    </span>
                    <span class="font-semibold text-slate-900 mt-1 block">
                        {{ sale.customer.full_name || sale.customer.name }}
                    </span>
                </div>
            </div>

            <!-- Bảng Danh Sách Sản Phẩm -->
            <div class="overflow-x-auto rounded-xl border border-slate-200 mb-6">
                <table class="w-full text-sm text-left">
                    <thead>
                        <tr class="bg-slate-50 text-xs font-bold uppercase text-slate-500 border-b border-slate-200">
                            <th class="py-3 px-4">Sản phẩm</th>
                            <th class="py-3 px-4">IMEI</th>
                            <th class="py-3 px-4 text-center">SL</th>
                            <th class="py-3 px-4 text-right">Đơn giá</th>
                            <th class="py-3 px-4 text-right">Thành tiền</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="item in sale.items"
                            :key="item.id"
                            class="hover:bg-slate-50/50 transition"
                        >
                            <td class="py-3 px-4">
                                <div class="font-bold text-slate-900">
                                    {{ item.product?.name }}
                                </div>
                                <div
                                    v-if="baseQuantityText(item)"
                                    class="mt-0.5 text-xs font-semibold text-indigo-600"
                                >
                                    {{ baseQuantityText(item) }}
                                </div>
                                <span
                                    v-if="item.variant?.attributes"
                                    class="inline-block mt-0.5 text-xs font-semibold text-indigo-600 bg-indigo-50 px-1.5 py-0.5 rounded"
                                >
                                    {{ Object.values(item.variant.attributes).filter(Boolean).join(' / ') }}
                                </span>
                            </td>

                            <td class="py-3 px-4 font-mono text-xs text-slate-600">
                                {{ item.product_imei?.imei ?? '-' }}
                            </td>

                            <td class="py-3 px-4 text-center font-bold text-slate-800">
                                {{ saleQuantityText(item) }}
                            </td>

                            <td class="py-3 px-4 text-right text-slate-700 font-medium">
                                {{ formatMoney(item.unit_price) }}
                            </td>

                            <td class="py-3 px-4 text-right font-bold text-slate-900">
                                {{ formatMoney(item.subtotal) }}
                            </td>
                        </tr>
                    </tbody>

                    <tfoot class="bg-slate-50 font-bold border-t border-slate-200">
                        <tr>
                            <td colspan="4" class="py-3 px-4 text-right text-xs uppercase text-slate-600">
                                Tổng cộng
                            </td>
                            <td class="py-3 px-4 text-right text-indigo-600 text-base">
                                {{ formatMoney(sale.subtotal || sale.grand_total) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Footer cảm ơn -->
            <div class="text-center pt-4 border-t border-slate-100 text-xs text-slate-400">
                <p class="font-semibold">CẢM ƠN QUÝ KHÁCH VÀ HẸN GẶP LẠI!</p>
            </div>
        </div>
    </div>
</template>
