<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({

    repairs: Object,
});
</script>

<template>
    <div class="p-6">

        <div class="flex justify-between mb-6">

            <h1 class="text-2xl font-bold">
                Tiếp nhận sửa chữa
            </h1>

            <Link
                :href="route('repairs.create')"
                class="bg-blue-600 text-white px-5 py-2 rounded"
            >
                Nhận máy
            </Link>
        </div>

        <div class="border rounded overflow-hidden">

            <table class="w-full">

                <thead class="bg-gray-100">

                    <tr>

                        <th class="p-3 text-left">
                            Mã phiếu
                        </th>

                        <th class="p-3 text-left">
                            Khách hàng
                        </th>

                        <th class="p-3 text-left">
                            Thiết bị
                        </th>

                        <th class="p-3 text-left">
                            Trạng thái
                        </th>

                        <th class="p-3 text-left">
                            Ngày
                        </th>
                    </tr>
                </thead>

                <tbody>

                    <tr
                        v-for="repair in repairs.data"
                        :key="repair.id"
                        class="border-t"
                    >

                        <td class="p-3">
                            {{ repair.code }}
                        </td>

                        <td class="p-3">
                            {{ repair.customer_name }}
                        </td>

                        <td class="p-3">
                            {{ repair.device_name }}
                        </td>

                        <td class="p-3">

                            <span
                                v-if="repair.status === 'pending'"
                                class="text-yellow-600"
                            >
                                Chờ sửa
                            </span>

                            <span
                                v-else-if="repair.status === 'repairing'"
                                class="text-blue-600"
                            >
                                Đang sửa
                            </span>

                            <span
                                v-else-if="repair.status === 'completed'"
                                class="text-green-600"
                            >
                                Hoàn thành
                            </span>

                            <span
                                v-else
                                class="text-gray-600"
                            >
                                Đã trả
                            </span>
                        </td>

                        <td class="p-3">
                            {{ repair.created_at }}
                        </td>
                    </tr>

                    <tr v-if="!repairs.data.length">

                        <td
                            colspan="5"
                            class="p-5 text-center text-gray-500"
                        >
                            Chưa có phiếu sửa chữa
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>