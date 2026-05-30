<script setup>
import { Link, router } from '@inertiajs/vue3';

defineProps({
    products: Object,
});
// Xóa sản phẩm
const destroy = (id) => {

    if (confirm('Xóa sản phẩm?')) {

        router.delete(
            route('products.destroy', id)
        )
    }
}
</script>

<template>
    <div class="p-6">
        <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center justify-between mb-4">
            <h1 class="text-2xl font-bold">
                Danh sách sản phẩm
            </h1>

            <Link
                :href="route('products.create')"
                class="bg-blue-600 text-white px-4 py-2 rounded"
            >
                Thêm sản phẩm
            </Link>
            
            <Link
                :href="route('products.trash')"
                class="bg-red-600 text-white px-4 py-2 rounded"
            >
                Thùng rác
            </Link>
            
        </div>
        </div>

        <table class="w-full border border-gray-300">
            <thead class="bg-gray-100">
                <tr>
                    <th class="border p-2 text-left">
                        ID
                    </th>

                    <th class="border p-2 text-left">
                        Tên sản phẩm
                    </th>
                    <th class="px-4 py-3 text-left">
                        Ảnh
                    </th>
                    <th class="border p-2">
                        Danh mục
                    </th>
                    <th class="border p-2">
                        Thương hiệu
                    </th>
                    <th class="border p-2 text-left">
                        Giá bán
                    </th>
                    <th class="border p-2 text-left">
                        Tồn kho
                    </th>
                    <th class="border p-2 text-left">
                        Thao tác
                    </th>
                </tr>
            </thead>

            <tbody>
                <tr
                    v-for="product in products.data"
                    :key="product.id"
                >
                    <td class="border p-2">
                        {{ product.id }}
                    </td>

                    <td class="border p-2">
                        {{ product.name }}
                    </td>

                    <td class="px-4 py-3">
                        <img
                            v-if="product.image_url"
                            :src="product.image_url"
                            class="w-16 h-16 object-cover rounded border"
                        >

                        <div
                            v-else
                            class="text-gray-400"
                        >
                            No image
                        </div>

                    </td>
                    <td class="border p-2">
                        {{ product.category?.name }}
                    </td>
                    <td class="border p-2">
                        {{ product.brand?.name || '-' }}
                    </td>
                    <td class="border p-2">
                        {{ product.price }}
                    </td>
                    <td class="border p-2">
                        {{ product.stock }}
                    </td>
                    <td class="border p-2">
                        <Link
                            :href="
                                route(
                                    'product-imeis.index',
                                    product.id
                                )
                            "
                            class="text-green-600"
                        >
                            IMEI
                        </Link>
                        <Link
                            :href="
                                route(
                                    'products.show',
                                    product.id
                                )
                            "
                            class="text-green-600 mr-3"
                        >
                            Xem
                        </Link>
                        <Link
                            :href="
                                route(
                                    'products.edit',
                                    product.id
                                )
                            "
                            class="text-blue-600"
                        >
                            Sửa
                        </Link>
                        <button
                            @click="destroy(product.id)"
                            class="text-red-600"
                        >
                            Xóa
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>