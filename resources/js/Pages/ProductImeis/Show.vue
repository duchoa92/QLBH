<script setup>

const props = defineProps({

    imei: Object,
})

</script>

<template>

    <div class="p-6">

        <h1 class="text-2xl font-bold mb-6">

            Chi tiết IMEI

        </h1>

        <div class="bg-white rounded shadow p-6">

            <div class="space-y-3">

                <p>

                    <strong>IMEI:</strong>

                    {{ imei.imei }}

                </p>

                <p>

                    <strong>Sản phẩm:</strong>

                    {{ imei.product?.name }}

                </p>

                <p>

                    <strong>Trạng thái:</strong>

                    {{ imei.status }}

                </p>

                <p>

                    <strong>Ngày bán:</strong>

                    {{ imei.sold_at ?? '-' }}

                </p>

            </div>

            <div
                v-if="
                    imei.sale_items &&
                    imei.sale_items.length
                "
                class="mt-8"
            >

                <h2 class="font-bold text-lg mb-4">

                    Lịch sử bán

                </h2>

                <table class="w-full">

                    <thead>

                        <tr>

                            <th class="text-left">
                                Hóa đơn
                            </th>

                            <th class="text-left">
                                Khách hàng
                            </th>

                            <th class="text-left">
                                Ngày bán
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                        <tr
                            v-for="
                                saleItem
                                in imei.sale_items
                            "
                            :key="saleItem.id"
                        >

                            <td>

                                {{
                                    saleItem.sale?.code
                                }}

                            </td>

                            <td>

                                {{
                                    saleItem.sale
                                        ?.customer
                                        ?.name
                                    ?? '-'
                                }}

                            </td>

                            <td>

                                {{
                                    saleItem.sale
                                        ?.created_at
                                }}

                            </td>

                        </tr>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</template>