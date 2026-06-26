<script setup>

defineProps({
    show: Boolean,
    invoice: {
        type: Object,
        default: null
    }
})

const emit = defineEmits([
    'close'
])

const money = (value) => {
    return Number(value || 0)
        .toLocaleString('vi-VN')
}

</script>


<template>
    <div
        v-if="show"
        class="fixed inset-0 bg-black/40 flex items-center justify-center z-50"
    >
        <div
            class="bg-white w-[800px] max-h-[85vh] overflow-auto rounded-xl shadow-lg p-5"
        >
            <div class="flex justify-between mb-4 items-center">
                <h2 class="font-bold text-lg text-gray-800">
                    Chi tiết hóa đơn
                </h2>
                <button
                    @click="emit('close')"
                    class="text-gray-400 hover:text-red-500 font-bold text-xl transition-colors px-2"
                >
                    &times;
                </button>
            </div>

            <div v-if="invoice">
                <div class="grid grid-cols-2 gap-2 text-sm bg-gray-50 p-3 rounded-lg border border-gray-100">
                    <div>Mã HĐ: <b class="text-blue-600">{{ invoice.code }}</b></div>
                    <div>Ngày: <span class="text-gray-600">{{ invoice.created_at }}</span></div>
                    <div>Khách hàng: <b>{{ invoice.customer?.full_name ?? 'Khách lẻ' }}</b></div>
                    <div>Nhân viên: <span class="text-gray-600">{{ invoice.user?.name ?? '' }}</span></div>
                </div>

                <hr class="my-4 border-gray-200">

                <table class="w-full text-sm border-collapse text-left">
                    <thead>
                        <tr class="border-b-2 border-gray-200 bg-gray-100 text-gray-700">
                            <th class="p-2 font-semibold text-center w-12">STT</th>
                            <th class="p-2 font-semibold">Tên sản phẩm / Quà tặng</th>
                            <th class="p-2 font-semibold text-center w-28">Phân loại</th>
                            <th class="p-2 font-semibold text-center w-16">SL</th>
                            <th class="p-2 font-semibold text-right w-28">Đơn giá</th>
                            <th class="p-2 font-semibold text-right w-28">Thành tiền</th>
                        </tr>
                    </thead>

                    <tbody>
                        <template v-for="(item, index) in invoice.items" :key="item.id">
                            
                            <tr class="border-b hover:bg-gray-50/50">
                                <td class="p-2 text-center text-gray-500">{{ index + 1 }}</td>
                                <td class="p-2">
                                    <div class="font-medium text-gray-900">
                                        {{ item.product?.name }}
                                    </div>
                                    
                                    <div v-if="item.product_imei" class="text-xs text-blue-600 mt-0.5">
                                        IMEI: {{ item.product_imei.imei }}
                                    </div>

                                    <div v-if="Nutmbe = Number(item.discount_value) > 0" class="text-xs text-red-500 mt-0.5 font-medium">
                                        <template v-if="item.discount_type === 'percent'">
                                            Giảm {{ item.discount_value }}%
                                        </template>
                                        <template v-else-if="item.discount_type === 'amount'">
                                            Giảm {{ money(item.discount_value) }} đ
                                        </template>
                                    </div>

                                    <div v-if="item.note" class="text-xs text-gray-400 italic mt-0.5">
                                        Ghi chú: {{ item.note }}
                                    </div>
                                </td>
                                <td class="p-2 text-center">
                                    <span class="inline-block px-2 py-0.5 text-xs font-semibold rounded bg-blue-50 text-blue-600 border border-blue-100">
                                        Sản phẩm
                                    </span>
                                </td>
                                <td class="p-2 text-center font-medium">{{ item.quantity }}</td>
                                <td class="p-2 text-right text-gray-600">{{ money(item.unit_price) }} đ</td>
                                <td class="p-2 text-right font-semibold text-gray-900">{{ money(item.subtotal) }} đ</td>
                            </tr>

                            <template v-if="item.gifts && item.gifts.length">
                                <tr 
                                    v-for="(gift, gIdx) in item.gifts" 
                                    :key="'gift-' + gift.id + '-' + gIdx"
                                    class="border-b bg-green-50/20 text-gray-600"
                                >
                                    <td class="p-2 text-center text-gray-400 text-xs">-</td>
                                    <td class="p-2 pl-6 text-gray-700 italic">
                                        🎁 {{ gift.product?.name }}
                                    </td>
                                    <td class="p-2 text-center">
                                        <span class="inline-block px-2 py-0.5 text-xs font-semibold rounded bg-green-50 text-green-600 border border-green-100">
                                            Quà tặng
                                        </span>
                                    </td>
                                    <td class="p-2 text-center">{{ gift.quantity }}</td>
                                    <td class="p-2 text-right text-xs text-gray-400 line-through">
                                        {{ money(gift.product?.retail_price || gift.product?.price) }} đ
                                    </td>
                                    <td class="p-2 text-right font-medium text-green-600">0 đ</td>
                                </tr>
                            </template>

                            <tr 
                                v-if="item.gift_product" 
                                class="border-b bg-green-50/20 text-gray-600"
                            >
                                <td class="p-2 text-center text-gray-400 text-xs">-</td>
                                <td class="p-2 pl-6 text-gray-700 italic">
                                    🎁 {{ item.gift_product.name }}
                                </td>
                                <td class="p-2 text-center">
                                    <span class="inline-block px-2 py-0.5 text-xs font-semibold rounded bg-green-50 text-green-600 border border-green-100">
                                        Quà tặng
                                    </span>
                                </td>
                                <td class="p-2 text-center">1</td>
                                <td class="p-2 text-right text-xs text-gray-400 line-through">
                                    {{ money(item.gift_product.retail_price || item.gift_product.price) }} đ
                                </td>
                                <td class="p-2 text-right font-medium text-green-600">0 đ</td>
                            </tr>

                        </template>
                    </tbody>
                </table>

                <hr class="my-4 border-gray-200">

                <div class="space-y-1.5 text-right text-sm text-gray-600 max-w-md ml-auto">
                    <div class="flex justify-between pl-10">
                        <span>Tiền hàng:</span>
                        <span class="font-medium text-gray-900">{{ money(invoice.subtotal) }} đ</span>
                    </div>

                    <div class="flex justify-between pl-10 text-red-500">
                        <span>Giảm giá hóa đơn:</span>
                        <span class="font-medium">-{{ money(invoice.discount) }} đ</span>
                    </div>

                    <div class="flex justify-between pl-10 text-base font-bold text-blue-600 pt-1 border-t border-dashed mt-1">
                        <span>Tổng thanh toán:</span>
                        <span>{{ money(invoice.grand_total) }} đ</span>
                    </div>

                    <div class="flex justify-between pl-10 pt-1 text-gray-500">
                        <span>Khách trả:</span>
                        <span>{{ money(invoice.paid_amount) }} đ</span>
                    </div>

                    <div class="flex justify-between pl-10 text-gray-500">
                        <span>Tiền thừa:</span>
                        <span>{{ money(invoice.change_amount) }} đ</span>
                    </div>

                    <div class="flex justify-between pl-10 text-xs text-gray-400 pt-1">
                        <span>Phương thức thanh toán:</span>
                        <span class="uppercase font-medium text-gray-600">{{ invoice.payment_method }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>