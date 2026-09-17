<script setup>
import { formatDateTime } from '@/utils/format'

const props = defineProps({
    sale: {
        type: Object,
        required: true
    }
});

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

    return `${Number.isInteger(qty) ? qty : qty.toLocaleString('vi-VN')} ${unit}`
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
                class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium text-sm px-5 py-2.5 rounded-xl transition-all shadow-sm active:scale-95"
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
                    ĐỨC HÒA COMPUTER
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
                                class="inline-block mt-0.5 text-xs text-indigo-600 font-medium bg-indigo-50 px-1.5 py-0.5 rounded"
                            >
                                {{ variantLabel(item.variant) }}
                            </div>

                            <div
                                v-if="item.product_imei"
                                class="text-xs text-slate-500 font-mono mt-0.5"
                            >
                                IMEI: {{ item.product_imei.imei }}
                            </div>
                        </td>

                        <td class="text-center py-3 text-slate-600 font-medium">
                            {{ saleQuantityText(item) }}
                        </td>

                        <td class="text-right py-3 font-semibold text-slate-800">
                            {{ Number(item.subtotal ?? (item.unit_price * item.quantity)).toLocaleString('vi-VN') }}đ
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Tổng kết tiền thanh toán -->
            <div class="space-y-2.5 pt-4 border-t border-slate-200 text-sm">
                <div class="flex justify-between text-slate-600">
                    <span>Tổng tiền hàng</span>
                    <span class="font-semibold text-slate-800">
                        {{ Number(sale.grand_total).toLocaleString('vi-VN') }}đ
                    </span>
                </div>

                <div class="flex justify-between text-slate-600">
                    <span>Khách thanh toán</span>
                    <span class="font-semibold text-slate-800">
                        {{ Number(sale.paid_amount).toLocaleString('vi-VN') }}đ
                    </span>
                </div>

                <div class="flex justify-between items-center pt-2 border-t border-dashed border-slate-200 text-base font-bold text-slate-900">
                    <span>Tiền thừa trả khách</span>
                    <span class="text-indigo-600 text-lg">
                        {{ Number(sale.change_amount).toLocaleString('vi-VN') }}đ
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