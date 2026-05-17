<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    product: Object,
});
</script>

<template>

    <div class="p-6">

        <div
            class="flex items-center justify-between mb-6"
        >

            <h1 class="text-2xl font-bold">
                Chi tiết sản phẩm
            </h1>

            <Link
                :href="route('products.index')"
                class="px-4 py-2 bg-gray-200 rounded"
            >
                Quay lại
            </Link>

        </div>

        <div
            class="bg-white rounded shadow p-6 mb-6"
        >

            <div class="grid grid-cols-2 gap-4">

                <div>
                    <strong>Tên:</strong>
                    {{ product.name }}
                </div>

                <div>
                    <strong>SKU:</strong>
                    {{ product.sku }}
                </div>

                <div>
                    <strong>Danh mục:</strong>
                    {{ product.category?.name }}
                </div>

                <div>
                    <strong>Thương hiệu:</strong>
                    {{ product.brand?.name }}
                </div>

                <div>
                    <strong>Giá nhập:</strong>
                    {{ product.cost_price }}
                </div>

                <div>
                    <strong>Giá bán:</strong>
                    {{ product.sell_price }}
                </div>

                <div>
                    <strong>Tồn kho:</strong>
                    {{ product.stock }}
                </div>

            </div>

        </div>

        <div
            class="bg-white rounded shadow p-6"
        >

            <h2 class="text-xl font-bold mb-4">
                Danh sách IMEI
            </h2>

            <table class="w-full border">

                <thead>

                    <tr class="bg-gray-100">

                        <th class="border p-2">
                            ID
                        </th>

                        <th class="border p-2">
                            IMEI
                        </th>

                        <th class="border p-2">
                            Trạng thái
                        </th>

                    </tr>

                </thead>

                <tbody>

                    <tr
                        v-for="imei in product.imeis"
                        :key="imei.id"
                    >

                        <td class="border p-2">
                            {{ imei.id }}
                        </td>

                        <td class="border p-2">
                            {{ imei.imei }}
                        </td>

                        <td class="border p-2">

                            <span
                                v-if="
                                    imei.status === 'in_stock'
                                "
                                class="text-green-600"
                            >
                                Trong kho
                            </span>

                            <span
                                v-else-if="
                                    imei.status === 'sold'
                                "
                                class="text-red-600"
                            >
                                Đã bán
                            </span>

                            <span
                                v-else
                                class="text-yellow-600"
                            >
                                Sửa chữa
                            </span>

                        </td>

                    </tr>

                </tbody>

            </table>

        </div>

    </div>

</template>