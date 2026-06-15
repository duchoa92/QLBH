<script setup>

defineProps({

    show:Boolean,

    invoice:{
        type:Object,
        default:null
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
        class="bg-white w-[750px] max-h-[85vh] overflow-auto rounded-xl shadow-lg p-5"
    >


    <div class="flex justify-between mb-4">

    <h2 class="font-bold text-lg">
        Chi tiết hóa đơn
    </h2>


    <button
        @click="emit('close')"
        class="text-red-500 font-bold"
    >
        X
    </button>


    </div>


    <div v-if="invoice">


    <div class="grid grid-cols-2 gap-2 text-sm">


    <div>

    Mã HĐ:

    <b>
    {{ invoice.code }}
    </b>

    </div>


    <div>Ngày:{{ invoice.created_at }}</div>


    <div>Khách hàng:<b>{{ invoice.customer?.full_name ?? 'Khách lẻ' }}</b></div>


    <div>Nhân viên:{{ invoice.user?.name ?? '' }}</div>


    </div>


    <hr class="my-4">


    <table
    class="w-full text-sm"
    >


    <thead>
        <tr class="border-b bg-gray-100">
            <th class="text-left p-2">Sản phẩm</th>
            <th>SL</th>
            <th class="text-right" >Đơn giá</th>
            <th class="text-right">Thành tiền</th>
        </tr>

    </thead>

        <tbody>
            <tr
                v-for="item in invoice.items"
                :key="item.id"
                class="border-b"
            >

                <td class="p-2">
                    <div class="font-medium">
                        {{ item.product?.name }}
                    </div>

                    <div
                        v-if="item.product_imei"
                        class="text-xs text-blue-600"
                    >
                        IMEI:
                        {{ item.product_imei.imei }}
                    </div>

                    <div
                        v-if="item.gift_product"
                        class="text-xs text-green-600"
                    >
                        🎁
                        {{ item.gift_product.name }}
                    </div>

                    <div
                        v-if="Number(item.discount_value) > 0"
                        class="text-xs text-red-600"
                    >

                        <template
                            v-if="
                                item.discount_type === 'percent'
                            "
                        >
                            Giảm
                            {{ item.discount_value }}%
                        </template>

                        <template
                            v-else-if="
                                item.discount_type === 'amount'
                            "
                        >
                            Giảm
                            {{ money(item.discount_value) }}
                            đ
                        </template>

                    </div>

                    <div
                        v-if="item.note"
                        class="text-xs text-gray-500 italic"
                    >
                        Ghi chú:
                        {{ item.note }}
                    </div>

                </td>
                <td class="text-center">
                    {{ item.quantity }}
                </td>

                <td class="text-right">
                    {{ money(item.unit_price) }}
                </td>

                <td class="text-right">
                    {{ money(item.subtotal) }}
                </td>
            </tr>
        </tbody>
    </table>

    <hr class="my-4">

    <div class="space-y-1 text-right">
        <div>
            Tiền hàng: <b>{{ money(invoice.subtotal) }}</b>
        </div>

        <div>
            Giảm giá: <b>{{ money(invoice.discount) }}</b>
        </div>

        <div class="text-lg font-bold text-blue-600">
            Tổng: {{ money(invoice.grand_total) }}
        </div>

        <div>
            Khách trả: {{ money(invoice.paid_amount) }}
        </div>

        <div>
            Tiền thừa: {{ money(invoice.change_amount) }}
        </div>

        <div>
            Thanh toán: {{ invoice.payment_method }}
        </div>
    </div>
    </div>
    </div>
    </div>

</template>