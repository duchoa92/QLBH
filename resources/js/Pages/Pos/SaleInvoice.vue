<script setup>
import { formatDateTime } from '@/utils/format'

const props = defineProps({

    sale: Object,
});

const printInvoice = () => {

    window.print();
};
</script>

<template>
    <div class="max-w-xl mx-auto p-6">

        <div class="flex justify-between mb-5 print:hidden">

            <h1 class="text-2xl font-bold">
                Hóa đơn
            </h1>

            <button
                @click="printInvoice"
                class="bg-blue-600 text-white px-5 py-2 rounded"
            >
                In hóa đơn
            </button>
        </div>

        <div class="border rounded p-5">

            <div class="text-center mb-6">

                <h2 class="text-xl font-bold">
                    Đức Hòa Computer
                </h2>

                <p>
                    Hóa đơn bán hàng
                </p>
            </div>

            <div class="space-y-1 mb-5">

                <p>
                    Mã HD:
                    {{ sale.code }}
                </p>

                <p>
                    Ngày:
                    {{ formatDateTime(sale.created_at) }}
                </p>
            </div>

            <table class="w-full mb-5">

                <thead>

                    <tr class="border-b">

                        <th class="text-left py-2">
                            Sản phẩm
                        </th>

                        <th class="text-center py-2">
                            SL
                        </th>

                        <th class="text-right py-2">
                            Thành tiền
                        </th>
                    </tr>
                </thead>

                <tbody>

                    <tr
                        v-for="item in sale.items"
                        :key="item.id"
                        class="border-b border-dashed"
                    >

                        <td class="py-2">
                            {{ item.product?.name }}

                            <span
                                v-if="item.variant?.attributes"
                                class="block text-xs text-indigo-600"
                            >
                                {{ Object.values(item.variant.attributes).filter(Boolean).join(' / ') }}
                            </span>

                            <span
                                v-if="item.product_imei"
                                class="block text-xs text-blue-600"
                            >
                                IMEI: {{ item.product_imei.imei }}
                            </span>
                        </td>

                        <td class="text-center py-2">
                            {{ item.quantity }}
                        </td>

                        <td class="text-right py-2">
                            {{ Number(item.subtotal ?? (item.unit_price * item.quantity)).toLocaleString() }}
                        </td>
                    </tr>
                </tbody>
            </table>

            <div class="space-y-2">

                <div class="flex justify-between">

                    <span>Tổng tiền</span>

                    <strong>
                        {{ Number(sale.grand_total).toLocaleString() }}
                    </strong>
                </div>

                <div class="flex justify-between">

                    <span>Khách đưa</span>

                    <strong>
                        {{ Number(sale.paid_amount).toLocaleString() }}
                    </strong>
                </div>

                <div class="flex justify-between">

                    <span>Tiền thừa</span>

                    <strong>
                        {{ Number(sale.change_amount).toLocaleString() }}
                    </strong>
                </div>
            </div>
        </div>
    </div>
</template>