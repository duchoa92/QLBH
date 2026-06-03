<script setup>
const props = defineProps({

    sale: Object,
});
</script>

<template>
    <div class="p-6 max-w-3xl">

        <h1 class="text-2xl font-bold mb-6">
            Chi tiết hóa đơn
        </h1>

        <div class="border rounded p-5">

            <div class="space-y-2 mb-5">

                <p>
                    <strong>Mã HD:</strong>
                    {{ sale.code }}
                </p>

                <p>
                    <strong>Ngày:</strong>
                    {{ sale.created_at }}
                </p>

            </div>

            <table class="w-full">

                <thead>

                    <tr class="border-b">

                        <th class="py-2 text-left">
                            Sản phẩm
                        </th>

                        <th class="py-2 text-left">
                            IMEI
                        </th>

                        <th class="py-2 text-center">
                            SL
                        </th>

                        <th class="py-2 text-right">
                            Đơn giá
                        </th>

                        <th class="py-2 text-right">
                            Thành tiền
                        </th>

                    </tr>

                </thead>

                <tbody>

                    <tr
                        v-for="item in sale.items"
                        :key="item.id"
                    >

                        <td class="py-2">

                            {{ item.product?.name }}

                        </td>

                        <td class="py-2">

                            {{ item.imei?.imei ?? '-' }}

                        </td>

                        <td class="py-2 text-center">

                            {{ item.quantity }}

                        </td>

                        <td class="py-2 text-right">

                            {{
                                Number(
                                    item.unit_price
                                ).toLocaleString(
                                    'vi-VN'
                                )
                            }}

                        </td>

                        <td class="py-2 text-right">

                            {{
                                Number(
                                    item.subtotal
                                ).toLocaleString(
                                    'vi-VN'
                                )
                            }}

                        </td>

                    </tr>

                </tbody>

                <tfoot>

                    <tr class="border-t">

                        <td
                            colspan="4"
                            class="text-right font-bold py-3"
                        >
                            Tổng cộng
                        </td>

                        <td class="text-right font-bold">

                            {{
                                Number(
                                    sale.subtotal
                                ).toLocaleString(
                                    'vi-VN'
                                )
                            }}

                        </td>

                    </tr>

                </tfoot>

            </table>

        </div>

    </div>
</template>