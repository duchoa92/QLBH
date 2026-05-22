<script setup>
import { Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({

    repairs: Object,

    filters: Object,
});

const search = ref(
    props.filters?.search ?? ''
);

const status = ref(
    props.filters?.status ?? ''
);

let timeout = null;

watch(

    [search, status],

    () => {

        clearTimeout(timeout);

        timeout = setTimeout(() => {

            router.get(

                route('repairs.index'),

                {

                    search: search.value,

                    status: status.value,
                },

                {

                    preserveState: true,

                    replace: true,
                }
            );

        }, 300);
    }
);

const statusClasses = {

    pending:
        'bg-yellow-100 text-yellow-700',

    checking:
        'bg-blue-100 text-blue-700',

    waiting_parts:
        'bg-orange-100 text-orange-700',

    repairing:
        'bg-indigo-100 text-indigo-700',

    done:
        'bg-green-100 text-green-700',

    returned:
        'bg-gray-100 text-gray-700',

    cancelled:
        'bg-red-100 text-red-700',
};

const statusLabels = {

    pending: 'Mới nhận',

    checking: 'Đang kiểm tra',

    waiting_parts: 'Chờ linh kiện',

    repairing: 'Đang sửa',

    done: 'Hoàn thành',

    returned: 'Đã trả khách',

    cancelled: 'Đã hủy',
};
</script>

<template>

    <div class="min-h-screen bg-gray-100">

        <!-- HEADER -->

        <div
            class="sticky top-0 z-40 bg-white border-b border-gray-200 shadow-sm"
        >

            <div
                class="max-w-7xl mx-auto px-4 md:px-6 py-4 flex flex-col md:flex-row md:items-center md:justify-between gap-4"
            >

                <div>

                    <h1
                        class="text-2xl md:text-3xl font-bold text-gray-800"
                    >
                        Phiếu sửa chữa
                    </h1>

                    <p
                        class="text-sm text-gray-500 mt-1"
                    >
                        Quản lý tiếp nhận và sửa chữa thiết bị
                    </p>

                </div>

                <Link
                    :href="route('repairs.create')"
                    class="px-5 py-3 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-semibold transition"
                >
                    + Nhận máy mới
                </Link>

            </div>

        </div>

        <div
            class="max-w-7xl mx-auto px-4 md:px-6 py-6"
        >

            <!-- FILTER -->

            <div
                class="bg-white rounded-3xl border border-gray-200 shadow-sm p-5 mb-6"
            >

                <div
                    class="grid grid-cols-1 md:grid-cols-3 gap-4"
                >

                    <!-- search -->

                    <div class="md:col-span-2">

                        <input
                            v-model="search"
                            type="text"
                            placeholder="Tìm mã phiếu, tên khách, IMEI..."
                            class="w-full rounded-2xl border border-gray-300 px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                        >

                    </div>

                    <!-- status -->

                    <div>

                        <select
                            v-model="status"
                            class="w-full rounded-2xl border border-gray-300 px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                        >

                            <option value="">
                                Tất cả trạng thái
                            </option>

                            <option value="pending">
                                Mới nhận
                            </option>

                            <option value="checking">
                                Đang kiểm tra
                            </option>

                            <option value="waiting_parts">
                                Chờ linh kiện
                            </option>

                            <option value="repairing">
                                Đang sửa
                            </option>

                            <option value="done">
                                Hoàn thành
                            </option>

                            <option value="returned">
                                Đã trả khách
                            </option>

                            <option value="cancelled">
                                Đã hủy
                            </option>

                        </select>

                    </div>

                </div>

            </div>

            <!-- TABLE -->

            <div
                class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden"
            >

                <div class="overflow-x-auto">

                    <table class="w-full">

                        <thead
                            class="bg-gray-50 border-b border-gray-200"
                        >

                            <tr>

                                <th
                                    class="text-left px-6 py-4 text-sm font-bold text-gray-600"
                                >
                                    Mã phiếu
                                </th>

                                <th
                                    class="text-left px-6 py-4 text-sm font-bold text-gray-600"
                                >
                                    Khách hàng
                                </th>

                                <th
                                    class="text-left px-6 py-4 text-sm font-bold text-gray-600"
                                >
                                    Thiết bị
                                </th>

                                <th
                                    class="text-left px-6 py-4 text-sm font-bold text-gray-600"
                                >
                                    IMEI
                                </th>

                                <th
                                    class="text-left px-6 py-4 text-sm font-bold text-gray-600"
                                >
                                    Trạng thái
                                </th>

                                <th
                                    class="text-left px-6 py-4 text-sm font-bold text-gray-600"
                                >
                                    Ngày nhận
                                </th>

                                <th
                                    class="text-right px-6 py-4 text-sm font-bold text-gray-600"
                                >
                                    Thao tác
                                </th>

                            </tr>

                        </thead>

                        <tbody>

                            <tr
                                v-for="repair in props.repairs.data"
                                :key="repair.id"
                                class="border-b border-gray-100 hover:bg-gray-50 transition"
                            >

                                <!-- code -->

                                <td class="px-6 py-4">

                                    <div
                                        class="font-bold text-blue-600"
                                    >
                                        {{ repair.code }}
                                    </div>

                                </td>

                                <!-- customer -->

                                <td class="px-6 py-4">

                                    <div
                                        class="font-semibold text-gray-800"
                                    >
                                        {{
                                            repair.customer_name
                                        }}
                                    </div>

                                    <div
                                        class="text-sm text-gray-500 mt-1"
                                    >
                                        {{
                                            repair.customer_phone
                                        }}
                                    </div>

                                </td>

                                <!-- device -->

                                <td class="px-6 py-4">

                                    <div
                                        class="font-medium text-gray-700"
                                    >
                                        {{
                                            repair.device_name
                                        }}
                                    </div>

                                </td>

                                <!-- imei -->

                                <td class="px-6 py-4">

                                    <div
                                        class="text-sm text-gray-600"
                                    >
                                        {{
                                            repair.imei || '---'
                                        }}
                                    </div>

                                </td>

                                <!-- status -->

                                <td class="px-6 py-4">

                                    <span
                                        :class="[

                                            'inline-flex items-center px-3 py-1 rounded-full text-xs font-bold',

                                            statusClasses[
                                                repair.status
                                            ]
                                        ]"
                                    >
                                        {{
                                            statusLabels[
                                                repair.status
                                            ]
                                        }}
                                    </span>

                                </td>

                                <!-- date -->

                                <td class="px-6 py-4">

                                    <div
                                        class="text-sm text-gray-600"
                                    >
                                        {{
                                            repair.created_at
                                        }}
                                    </div>

                                </td>

                                <!-- action -->

                                <td
                                    class="px-6 py-4 text-right"
                                >

                                    <div
                                        class="flex justify-end gap-2"
                                    >

                                        <Link
                                            :href="route('repairs.show', repair.id)"
                                            class="px-3 py-2 rounded-xl bg-gray-100 hover:bg-gray-200 text-sm"
                                        >
                                            Xem
                                        </Link>

                                        <Link
                                            :href="route('repairs.edit', repair.id)"
                                            class="px-3 py-2 rounded-xl bg-blue-100 hover:bg-blue-200 text-blue-700 text-sm"
                                        >
                                            Sửa
                                        </Link>

                                    </div>

                                </td>

                            </tr>

                            <!-- empty -->

                            <tr
                                v-if="!props.repairs.data.length"
                            >

                                <td
                                    colspan="7"
                                    class="text-center py-16 text-gray-400"
                                >

                                    Chưa có phiếu sửa chữa

                                </td>

                            </tr>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

    </div>

</template>