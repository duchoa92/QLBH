<script setup>
import { Link, router } from '@inertiajs/vue3'

defineProps({
    products: Object,
})

const restore = (id) => {

    if (confirm('Khôi phục sản phẩm?')) {

        router.post(
            route('products.restore', id)
        )
    }
}
</script>

<template>
    <div class="p-6">

        <div class="flex justify-between mb-6">

            <h1 class="text-2xl font-bold">
                Thùng rác sản phẩm
            </h1>

            <Link
                :href="route('products.index')"
                class="bg-gray-200 px-4 py-2 rounded"
            >
                Quay lại
            </Link>

        </div>

        <table class="w-full border">

            <thead class="bg-gray-100">

                <tr>
                    <th class="border p-2">ID</th>
                    <th class="border p-2">Tên</th>
                    <th class="border p-2">SKU</th>
                    <th class="border p-2">Thao tác</th>
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

                    <td class="border p-2">
                        {{ product.sku }}
                    </td>

                    <td class="border p-2">

                        <button
                            @click="restore(product.id)"
                            class="bg-green-600 text-white px-3 py-1 rounded"
                        >
                            Khôi phục
                        </button>

                    </td>
                </tr>

            </tbody>

        </table>

    </div>
</template>