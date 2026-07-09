<script setup>
import SortHeader from '@/Components/UI/SortHeader.vue'
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
    products: Object,
    selectedIds: Array,
    filters: Object
})

const isAllSelected = computed(() => {
    return props.products.data.length &&
        props.selectedIds.length === props.products.data.length
})

const emit = defineEmits([
    'toggleOne',
    'toggleAll',
    'open'
])

const format = (v) => new Intl.NumberFormat('vi-VN').format(v)


const go = (url) => {
    if (!url) return

    router.visit(url, {
        preserveState: true,
        preserveScroll: true
    })
}

const changePerPage = (perPage) => {
    router.get(route('products.index'), {
        ...filters.value,
        per_page: perPage
    }, {
        preserveState: true,
        replace: true
    })
}

</script>

<template>
    <div class="bg-white rounded-xl shadow border overflow-hidden">

        <table class="w-full text-sm border border-gray-200 rounded-lg overflow-hidden">

            <thead class="bg-gray-100 text-xs uppercase text-gray-600">
                <tr>
                    <th class="w-10 text-center border-r p-2">
                        <input
                            type="checkbox"
                            :checked="isAllSelected"
                            @change="$emit('toggleAll')"
                        />
                    </th>

                    <th class="w-10 text-center border-r p-2">ID</th>
                    <th class="w-10 text-center border-r p-2">
                        <SortHeader
                            label="Tên hàng hóa"
                            field="name"
                            :currentSort="filters.sort_by"
                            :currentOrder="filters.sort_order"
                            @sort="$emit('sort', $event)"
                        />
                    </th>
                    <th class="text-right border-r p-2">
                        <SortHeader
                            label="Giá bán lẻ"
                            field="sell_price"
                            :currentSort="filters.sort_by"
                            :currentOrder="filters.sort_order"
                            @sort="$emit('sort', $event)"
                        />
                    </th>
                    <th class="text-right border-r p-2">    
                        <SortHeader
                            label="Giá vốn"
                            field="cost_price"
                            :currentSort="filters.sort_by"
                            :currentOrder="filters.sort_order"
                            @sort="$emit('sort', $event)"
                        />
                    </th>
                    <th class="text-center border-r p-2">   
                        <SortHeader
                            label="Tồn kho"
                            field="stock"
                            :currentSort="filters.sort_by"
                            :currentOrder="filters.sort_order"
                            @sort="$emit('sort', $event)"
                        />
                    </th>
                    <th class="text-center border-r p-2">   
                        <SortHeader
                            label="Đã bán"
                            field="sold_quantity"
                            :currentSort="filters.sort_by"
                            :currentOrder="filters.sort_order"
                            @sort="$emit('sort', $event)"
                        />
                    </th>
                    <th class="text-center p-2">Tác vụ</th>
                </tr>
            </thead>

            <tbody>
                <tr
                    v-for="p in products.data"
                    :key="p.id"
                    class="border-t hover:bg-gray-50 cursor-pointer"
                    @click="$emit('open', p)"
                >
                    <td class="text-center border-r p-2">
                        <input
                            type="checkbox"
                            :checked="selectedIds.includes(p.id)"
                            @click.stop
                            @change="$emit('toggleOne', p.id)"
                        />
                    </td>

                    <td class="text-center border-r p-2">{{ p.id }}</td>

                    <!-- TÊN + ẢNH -->
                    <td class="border-r p-2">
                        <div class="flex items-center gap-2">
                            <img
                                :src="p.image || '/no-image.png'"
                                class="w-8 h-8 rounded object-cover border"
                            />
                            <span class="font-medium">{{ p.name }}</span>
                        </div>
                    </td>

                    <td class="text-right border-r p-2">{{ format(p.sell_price) }}</td>
                    <td class="text-right border-r p-2">{{ format(p.cost_price) }}</td>
                    <td class="text-center border-r p-2">{{ p.stock }}</td>
                    <td class="text-center border-r p-2">{{ p.sold_quantity }}</td>

                    <td class="text-center p-2 text-gray-400">--</td>
                </tr>
            </tbody>

        </table>

        <div class="flex items-center justify-between px-4 py-3 border-t bg-white text-sm">

    <!-- LEFT -->
    <div class="text-gray-500 w-1/3">
        Hiển thị {{ products.from }} - {{ products.to }} / {{ products.total }}
    </div>

    <!-- CENTER -->
    <div class="flex justify-center w-1/3">
        <div class="flex items-center gap-1">

            <button
                v-for="link in products.links"
                :key="link.label"
                v-html="link.label"
                @click="link.url && router.get(link.url, {}, { preserveState: true })"
                class="px-3 py-1 border rounded text-sm"
                :class="[
                    link.active
                        ? 'bg-blue-600 text-white border-blue-600'
                        : 'bg-white hover:bg-gray-100'
                ]"
            />

        </div>
    </div>

    <!-- RIGHT -->
    <div class="flex justify-end w-1/3">
        <select
            v-model="filters.per_page"
            class="border rounded px-2 py-1 text-sm"
        >
            <option :value="10">10</option>
            <option :value="20">20</option>
            <option :value="50">50</option>
            <option :value="100">100</option>
        </select>
    </div>

</div>

    </div>
</template>