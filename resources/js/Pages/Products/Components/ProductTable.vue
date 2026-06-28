<script setup>
import SortHeader from '@/Components/UI/SortHeader.vue'

defineProps({
    products: Object,
    selectedIds: Array,
    filters: Object
})

const emit = defineEmits([
    'toggleOne',
    'toggleAll',
    'open'
])
</script>

<template>
<div class="bg-white rounded-xl shadow border overflow-hidden">

<table class="w-full text-sm">

    <thead class="bg-gray-50 border-b text-xs uppercase">
        <tr>
            <th class="p-2">
                <input type="checkbox" @change="$emit('toggleAll')" />
            </th>

            <SortHeader label="ID" field="id" />
            <SortHeader label="Tên hàng" field="name" />

            <th class="p-2">Giá bán</th>
            <th class="p-2">Giá vốn</th>
            <th class="p-2">Đã bán</th>

            <SortHeader label="Tồn kho" field="stock" />

            <th></th>
        </tr>
    </thead>

    <tbody>
        <tr
            v-for="p in products.data"
            :key="p.id"
            @click="$emit('open', p)"
            class="border-b hover:bg-gray-50 cursor-pointer"
        >
            <td class="p-2">
                <input
                    type="checkbox"
                    @click.stop
                    @change="$emit('toggleOne', p.id)"
                />
            </td>

            <td>{{ p.id }}</td>
            <td>{{ p.name }}</td>
            <td>{{ p.sell_price }}</td>
            <td>{{ p.cost_price }}</td>
            <td>{{ p.sold_quantity }}</td>
            <td>{{ p.stock }}</td>
        </tr>
    </tbody>

</table>

</div>
</template>