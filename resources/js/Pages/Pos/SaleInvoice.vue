<script setup>
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
                    {{ sale.created_at }}
                </p>
            </div>

            <table class="w-full mb-5">

                <thead>

                    <tr class="border-b">

                        <th class="text-left py-2">
                            Sản phẩm
                        </th>

                        <th class="text-right py-2">
                            Giá
                        </th>
                    </tr>
                </thead>

                <tbody>

                    <tr
                        v-for="item in sale.items"
                        :key="item.id"
                    >

                        <td class="py-2">
                            {{ item.product.name }}
                        </td>

                        <td class="text-right">
                            {{ Number(item.price).toLocaleString() }}
                        </td>
                    </tr>
                </tbody>
            </table>

            <div class="space-y-2">

                <div class="flex justify-between">

                    <span>Tổng tiền</span>

                    <strong>
                        {{ Number(sale.total).toLocaleString() }}
                    </strong>
                </div>

                <div class="flex justify-between">

                    <span>Khách đưa</span>

                    <strong>
                        {{ Number(sale.customer_paid).toLocaleString() }}
                    </strong>
                </div>

                <div class="flex justify-between">

                    <span>Tiền thừa</span>

                    <strong>
                        {{ Number(sale.change_money).toLocaleString() }}
                    </strong>
                </div>
            </div>
        </div>
    </div>
</template>