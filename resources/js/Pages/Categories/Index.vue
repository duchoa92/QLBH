<script setup>
defineProps({
    categories: Object
})
</script>

<template>

    <div class="p-6">

        <div class="flex justify-between mb-5">
            <h1 class="text-2xl font-bold">
                Danh mục
            </h1>

            <a
                href="/categories/create"
                class="px-4 py-2 bg-black text-white rounded"
            >
                Thêm mới
            </a>
        </div>

        <table class="w-full border">

            <thead>
                <tr class="bg-gray-100">
                    <th class="border p-2">ID</th>
                    <th class="border p-2">Tên</th>
                    <th class="border p-2">Slug</th>
                </tr>
            </thead>

            <tbody>

                <tr
                    v-for="item in categories.data"
                    :key="item.id"
                >
                    <td class="border p-2">
                        {{ item.id }}
                    </td>

                    <td class="border p-2">
                        {{ item.name }}
                    </td>

                    <td class="border p-2">
                        {{ item.slug }}
                    </td>
                </tr>

            </tbody>

        </table>

    </div>

</template>