<script setup>
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'
import SortHeader from './SortHeader.vue'

const props = defineProps({
    data: { type: Object, default: () => ({ data: [] }) },
    columns: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    selectable: { type: Boolean, default: false },
    selectedIds: { type: Array, default: () => [] }
})

const emit = defineEmits([
    'toggleOne',
    'toggleAll',
    'sort'
])

const rows = computed(() => props.data?.data || [])
const isAllSelected = computed(() => {
    return rows.value.length > 0 &&
        props.selectedIds?.length === rows.value.length
})

const go = (url) => {
    if (!url) return
    router.visit(url, {
        preserveState: true,
        preserveScroll: true
    })
}
</script>

<template>
<div class="bg-white rounded-xl shadow border overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-[600px] w-full text-sm border border-gray-200 rounded-lg overflow-hidden">

            <!-- HEADER -->
            <thead>
                <tr class="bg-gray-100 text-left uppercase">

                    <!-- checkbox -->
                    <th v-if="selectable" class="w-10 text-center border-r p-2">
                        <input
                            type="checkbox"
                            :checked="isAllSelected"
                            @change="$emit('toggleAll')"
                        />
                    </th>

                    <!-- dynamic columns -->
                    <th 
                        v-for="col in columns" 
                        :key="col.key"
                        class="px-2 py-1 border-r text-left whitespace-nowrap"
                        :class="col.class"
                        :style="{ width: col.width }"
                    >
    
                        <SortHeader
                            v-if="col.sortable"
                            :label="col.label"
                            :field="col.key"
                            :currentSort="filters?.sort_by"
                            :currentOrder="filters?.sort_order"
                            @sort="$emit('sort', $event)"
                        />

                        <span v-else>
                            {{ col.label }}
                        </span>

                    </th>

                    <th class="text-center px-2 py-1 w-[90px]">Tác vụ</th>
                </tr>
            </thead>

            <!-- BODY -->
            <tbody>
                <tr v-for="row in rows"
                    :key="row.id"
                    class="border-t hover:bg-gray-50 cursor-pointer"
                >
                    <!-- checkbox -->
                    <td v-if="selectable" class="px-2 py-1 border-r">
                        <input
                            type="checkbox"
                            :checked="selectedIds.includes(row.id)"
                            @change="$emit('toggleOne', row.id)"
                        />
                    </td>

                    <!-- SLOT render row -->
                    <slot name="row" :row="row" />
                </tr>

                <tr v-if="rows.length === 0">
                    <td :colspan="columns.length + (selectable ? 2 : 1)"
                        class="text-center p-10 text-gray-500 bg-gray-50">
                        Không có dữ liệu
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- PAGINATION -->
    <div class="flex items-center justify-between px-4 py-3 border-t bg-white text-sm">
        <div class="text-gray-500 w-1/3">
            Trang {{ data.from }} - {{ data.to }} / {{ data.total }}
        </div>

        <div class="flex justify-center w-full">
            <div class="flex items-center gap-1">
                <button
                    v-for="link in data.links"
                    :key="link.label"
                    v-html="link.label"
                    @click="go(link.url)"
                    class="px-3 py-1 border rounded text-sm"
                    :class="link.active
                        ? 'bg-blue-600 text-white border-blue-600'
                        : 'bg-white hover:bg-gray-100'"
                />
            </div>
        </div>

        <div class="flex justify-end w-1/3">
            <select
                v-model="filters.per_page"
                class="border rounded px-3 py-1 text-sm"
            >
                <option :value="10">10</option>
                <option :value="20">20</option>
                <option :value="50">50</option>
            </select>
        </div>
    </div>
</div>
</template>