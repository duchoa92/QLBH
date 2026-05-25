<script setup>
import { router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'

const props = defineProps({
    customers: Object,
    filters: Object,
})

const search = ref(props.filters.search || '')

watch(search, (value) => {

    router.get(
        route('customers.index'),
        {
            search: value
        },
        {
            preserveState: true,
            replace: true,
        }
    )

})
</script>

<template>

    <div class="p-4">

        <!-- HEADER -->
        <div class="flex items-center justify-between mb-4">

            <div>
                <h1 class="text-2xl font-bold">
                    Khách hàng
                </h1>
            </div>

            <Link
                :href="route('customers.create')"
                class="bg-blue-600 text-white px-4 py-2 rounded"
            >
                + Thêm khách
            </Link>

        </div>

        <!-- SEARCH -->
        <div class="mb-4">

            <input
                v-model="search"
                type="text"
                placeholder="Tìm tên, SĐT..."
                class="w-full border rounded p-2"
            />

        </div>

        <!-- TABLE -->
        <div class="bg-white rounded shadow overflow-x-auto">

            <table class="w-full">

                <thead class="bg-gray-100">

                    <tr>

                        <th class="text-left p-3">
                            Mã KH
                        </th>

                        <th class="text-left p-3">
                            Họ tên
                        </th>

                        <th class="text-left p-3">
                            SĐT
                        </th>

                        <th class="text-left p-3">
                            Điểm
                        </th>

                        <th class="text-left p-3">
                            Công nợ
                        </th>

                        <th class="text-left p-3">
                            Loại
                        </th>

                    </tr>

                </thead>

                <tbody>

                    <tr
                        v-for="item in customers.data"
                        :key="item.id"
                        class="border-t hover:bg-gray-50"
                    >

                        <td class="p-3">
                            {{ item.code }}
                        </td>

                        <td class="p-3 font-medium">
                            {{ item.full_name }}
                        </td>

                        <td class="p-3">
                            {{ item.phone }}
                        </td>

                        <td class="p-3 text-blue-600">
                            {{ item.point_balance }}
                        </td>

                        <td
                            class="p-3"
                            :class="item.debt_balance > 0
                                ? 'text-red-600'
                                : 'text-green-600'"
                        >
                            {{ item.debt_balance }}
                        </td>

                        <td class="p-3">
                            {{ item.customer_type }}
                        </td>

                    </tr>

                </tbody>

            </table>

        </div>

    </div>

</template>