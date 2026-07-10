<script setup>
import { Link, router } from '@inertiajs/vue3'
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


            <Link
                :href="route('customers.create')"
                class="bg-blue-600 text-white px-4 py-2 rounded"
            >
                + Thêm khách
            </Link>

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
        <div class="bg-white rounded-xl shadow border overflow-hidden">
            <table class="w-full text-sm border border-gray-200 rounded-lg overflow-hidden">
                <thead>
                    <tr class="bg-gray-100 uppercase text-left">
                        <th class="w-10 text-center border-r p-2">Mã KH</th>
                        <th class="w-10 text-center border-r p-2">Họ tên</th>
                        <th class="w-10 text-center border-r p-2">SĐT</th>
                        <th class="w-10 text-center border-r p-2">Điểm</th>
                        <th class="w-10 text-center border-r p-2">Công nợ</th>
                        <th class="w-10 text-center border-r p-2">Loại</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="item in customers.data"
                        :key="item.id"
                        class="border-t hover:bg-gray-50"
                    >
                        <td class="text-center border-r p-2">{{ item.code }}</td>
                        <td class="text-center border-r p-2 font-medium">
                            <Link
                                :href="route('customers.show', item.id)"
                                class="text-blue-600 hover:underline"
                            >
                                {{ item.full_name }}
                            </Link>
                        </td>
                        <td class="text-center border-r p-2">{{ item.phone }}</td>
                        <td class="text-center border-r p-2 text-blue-600">{{ item.point_balance }}</td>
                        <td class="text-center border-r p-2"
                            :class="item.debt_balance > 0
                                ? 'text-red-600'
                                : 'text-green-600'"
                        >
                            {{ item.debt_balance }}
                        </td>
                        <td class="text-center border-r p-2">{{ item.customer_type }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</template>