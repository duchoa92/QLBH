<script setup>
import { onMounted } from 'vue'

const props = defineProps({

    sale: Object,
})

const formatMoney = (value) => {

    return Number(value || 0)
        .toLocaleString('vi-VN')
}

onMounted(() => {

    setTimeout(() => {

        window.print()

    }, 500)
})
</script>

<template>

    <div class="min-h-screen bg-gray-100 py-6">

        <div
            class="w-[80mm] mx-auto bg-white p-4 text-sm"
        >

            <!-- Shop -->
            <div class="text-center">

                <div class="font-bold text-lg">
                    SHOP ĐIỆN THOẠI
                </div>

                <div>
                    123 Hà Nội
                </div>

                <div>
                    0123456789
                </div>

            </div>

            <hr class="my-3">

            <!-- Invoice -->
            <div>

                <div>
                    Mã HĐ:
                    {{ sale.code }}
                </div>

                <div>
                    Ngày:
                    {{ sale.created_at }}
                </div>

            </div>

            <hr class="my-3">

            <!-- Items -->
            <table class="w-full text-sm">

                <thead>

                    <tr>

                        <th class="text-left">
                            SP
                        </th>

                        <th class="text-center">
                            SL
                        </th>

                        <th class="text-right">
                            Giá
                        </th>

                    </tr>

                </thead>

                <tbody>

                    <tr
                        v-for="item in sale.items"
                        :key="item.id"
                    >

                        <td>

                            <div>
                                {{ item.product?.name }}
                            </div>

                            <div
                                v-if="item.product_imei"
                                class="text-xs text-gray-500"
                            >
                                IMEI:
                                {{ item.product_imei?.imei }}
                            </div>

                        </td>

                        <td class="text-center">
                            {{ item.qty }}
                        </td>

                        <td class="text-right">
                            {{ formatMoney(item.price) }}
                        </td>

                    </tr>

                </tbody>

            </table>

            <hr class="my-3">

            <!-- Total -->
            <div class="space-y-1">

                <div class="flex justify-between">

                    <span>
                        Tạm tính
                    </span>

                    <span>
                        {{ formatMoney(sale.subtotal) }}
                    </span>

                </div>

                <div class="flex justify-between">

                    <span>
                        Giảm giá
                    </span>

                    <span>
                        {{ formatMoney(sale.discount) }}
                    </span>

                </div>

                <div
                    class="flex justify-between font-bold text-base"
                >

                    <span>
                        Tổng tiền
                    </span>

                    <span>
                        {{ formatMoney(sale.total) }}
                    </span>

                </div>

                <div class="flex justify-between">

                    <span>
                        Khách đưa
                    </span>

                    <span>
                        {{ formatMoney(sale.customer_paid) }}
                    </span>

                </div>

                <div class="flex justify-between">

                    <span>
                        Tiền thừa
                    </span>

                    <span>
                        {{ formatMoney(sale.change_money) }}
                    </span>

                </div>

            </div>

            <hr class="my-3">

            <!-- Footer -->
            <div class="text-center text-xs">

                Cảm ơn quý khách ❤️

            </div>

        </div>

    </div>

</template>