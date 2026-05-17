<script setup>
import { Link, router } from '@inertiajs/vue3';

defineProps({
    brands: Object,
});

const destroy = (id) => {

    if (!confirm('Xóa thương hiệu này?')) {
        return;
    }

    router.delete(
        route('brands.destroy', id)
    );
};
</script>

<template>
    <div class="p-6">

        <div
            class="flex items-center justify-between mb-6"
        >

            <h1 class="text-2xl font-bold">
                Thương hiệu
            </h1>

            <div class="flex gap-2">

                <Link
                    :href="route('brands.trash')"
                    class="bg-gray-700 text-white px-4 py-2 rounded"
                >
                    Thùng rác
                </Link>

                <Link
                    :href="route('brands.create')"
                    class="bg-blue-600 text-white px-4 py-2 rounded"
                >
                    Thêm thương hiệu
                </Link>

            </div>
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

                        <th class="p-3 text-left">
                            Trạng thái
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

                        <td class="p-3">

                            <span
                                v-if="brand.is_active"
                                class="text-green-600"
                            >
                                Hoạt động
                            </span>

                            <span
                                v-else
                                class="text-red-600"
                            >
                                Tạm khóa
                            </span>
                        </td>

                        <td
                            class="p-3 flex justify-end gap-2"
                        >
                            <Link
                                :href="
                                    route(
                                        'brands.edit',
                                        brand.id
                                    )
                                "
                                class="px-3 py-1 bg-yellow-500 text-white rounded"
                            >
                                Sửa
                            </Link>

                            <button
                                @click="destroy(brand.id)"
                                class="px-3 py-1 bg-red-600 text-white rounded"
                            >
                                Xóa
                            </button>
                        </td>
                    </tr>

                    <tr v-if="brands.data.length === 0">
                        <td
                            colspan="4"
                            class="p-6 text-center text-gray-500"
                        >
                            Chưa có dữ liệu
                        </td>
                    </tr>

                </tbody>
            </table>
        </div>
    </div>
</template>