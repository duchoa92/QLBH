<script setup>
import { Link, router } from '@inertiajs/vue3';

defineProps({
    brands: Object,
});

const restore = (id) => {

    router.post(
        route(
            'brands.restore',
            id
        )
    );
};
</script>

<template>
    <div class="p-6">

        <div
            class="flex items-center justify-between mb-6"
        >
            <h1 class="text-2xl font-bold">
                Thùng rác thương hiệu
            </h1>

            <Link
                :href="route('brands.index')"
                class="px-4 py-2 bg-gray-200 rounded"
            >
                Quay lại
            </Link>
        </div>

        <div class="bg-white rounded shadow overflow-hidden">

            <table class="w-full">

                <thead class="bg-gray-100">

                    <tr>
                        <th class="p-3 text-left">
                            ID
                        </th>

                        <th class="p-3 text-left">
                            Tên
                        </th>

                        <th class="p-3 text-right">
                            Thao tác
                        </th>
                    </tr>
                </thead>

                <tbody>

                    <tr
                        v-for="brand in brands.data"
                        :key="brand.id"
                        class="border-t"
                    >
                        <td class="p-3">
                            {{ brand.id }}
                        </td>

                        <td class="p-3">
                            {{ brand.name }}
                        </td>

                        <td
                            class="p-3 flex justify-end"
                        >
                            <button
                                @click="restore(brand.id)"
                                class="px-3 py-1 bg-green-600 text-white rounded"
                            >
                                Khôi phục
                            </button>
                        </td>
                    </tr>

                    <tr v-if="brands.data.length === 0">
                        <td
                            colspan="3"
                            class="p-6 text-center text-gray-500"
                        >
                            Không có dữ liệu
                        </td>
                    </tr>

                </tbody>
            </table>
        </div>
    </div>
</template>