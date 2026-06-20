<script setup>

import { Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({
    suppliers: Array,
    filters: Object,
})

const search = ref(
    props.filters?.search ?? ''
)

const searchSupplier = () => {

    router.get(
        '/suppliers',
        {
            search: search.value,
        },
        {
            preserveState: true,
            replace: true,
        }
    )
}

</script>

<template>

    <div class="p-6">

        <div
            class="
                flex
                justify-between
                items-center
                mb-6
            "
        >

            <h1
                class="
                    text-2xl
                    font-bold
                "
            >
                Nhà cung cấp
            </h1>

            <Link
                href="/suppliers/create"
                class="
                    px-4
                    py-2
                    bg-blue-600
                    text-white
                    rounded
                "
            >
                Thêm mới
            </Link>

        </div>
<!--ô Tìm kiếm nhà cung cấp-->
        <div class="mb-4">
            <input
                v-model="search"
                @input="searchSupplier"
                type="text"
                placeholder="Tìm tên, điện thoại, email"
                class="w-full border rounded px-3 py-2"
            />

        </div>

        <table
            class="
                w-full
                border
            "
        >

            <thead>

                <tr
                    class="
                        bg-gray-100
                    "
                >

                    <th class="border p-2">
                        Mã
                    </th>

                    <th class="border p-2">
                        Tên
                    </th>

                    <th class="border p-2">
                        Điện thoại
                    </th>

                    <th class="border p-2">
                        Công nợ
                    </th>

                    <th class="border p-2">
                        Thao tác
                    </th>

                </tr>

            </thead>

            <tbody>

                <tr
                    v-for="supplier in suppliers"
                    :key="supplier.id"
                >

                    <td class="border p-2">
                        {{ supplier.code }}
                    </td>

                    <td class="border p-2">
                        {{ supplier.name }}
                    </td>

                    <td class="border p-2">
                        {{ supplier.phone }}
                    </td>

                    <td class="border p-2">
                        {{ supplier.debt_balance }}
                    </td>

                    <td class="border p-2">

                        <Link
                            :href="
                                `/suppliers/${supplier.id}/edit`
                            "
                            class="
                                text-blue-600
                            "
                        >
                            Sửa
                        </Link>

                    </td>

                </tr>

            </tbody>

        </table>

    </div>

</template>