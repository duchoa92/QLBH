<script setup>
import { Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({

    sales: Object,

    filters: Object,
});

const search = ref(

    props.filters.search ?? ''
);

watch(search, value => {

    router.get(

        route('sales.index'),

        {
            search: value,
        },

        {
            preserveState: true,
            replace: true,
        }
    );
});
</script>

<template>
    <div class="p-6">

        <div class="flex justify-between mb-6">

            <h1 class="text-2xl font-bold">
                Hóa đơn bán hàng
            </h1>
        </div>

        <!-- search -->

        <div class="mb-5">

            <input
                v-model="search"
                type="text"
                placeholder="Tìm mã HD hoặc IMEI..."
                class="w-full border rounded p-3"
            >
        </div>

        <!-- table -->

        <div class="border rounded overflow-hidden">

            <table class="w-full">

                <thead class="bg-gray-100">

                    <tr>

                        <th class="p-3 text-left">
                            Mã HD
                        </th>

                        <th class="p-3 text-left">
                            Tổng tiền
                        </th>

                        <th class="p-3 text-left">
                            Ngày
                        </th>

                        <th class="p-3">
                        </th>
                    </tr>
                </thead>

                <tbody>

                    <tr
                        v-for="sale in sales.data"
                        :key="sale.id"
                        class="border-t"
                    >

                        <td class="p-3">
                            {{ sale.code }}
                        </td>

                        <td class="p-3">
                            {{ Number(sale.subtotal).toLocaleString() }}
                        </td>

                        <td class="p-3">
                            {{ sale.created_at }}
                        </td>

                        <td class="p-3 text-right">

                            <Link
                                :href="
                                    route(
                                        'sales.show',
                                        sale.id
                                    )
                                "
                                class="text-blue-600"
                            >
                                Xem
                            </Link>
                        </td>
                    </tr>

                    <tr v-if="!sales.data.length">

                        <td
                            colspan="4"
                            class="p-5 text-center text-gray-500"
                        >
                            Không có dữ liệu
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>