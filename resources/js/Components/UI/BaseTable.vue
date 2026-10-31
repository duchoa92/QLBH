<script setup>
import { computed } from 'vue'
import SortHeader from './SortHeader.vue'
import PaginationBar from './PaginationBar.vue'

const props = defineProps({
    data: { type: Object, default: () => ({ data: [] }) },
    columns: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    selectable: { type: Boolean, default: false },
    rowClickable: { type: Boolean, default: false },
    hidePaginationWhenSinglePage: { type: Boolean, default: false },
    selectedIds: { type: Array, default: () => [] }
})

const emit = defineEmits([
    'toggleOne',
    'toggleAll',
    'sort',
    'rowClick'
])

const rows = computed(() => props.data?.data || [])
const isAllSelected = computed(() => rows.value.length > 0 && rows.value.every(row => props.selectedIds?.includes(row.id)))

</script>

<template>
<div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="app-data-table min-w-[500px] w-full text-sm">

            <!-- HEADER -->
            <thead>
                <tr class="bg-white text-left text-xs font-semibold uppercase tracking-wide text-slate-600">

                    <!-- checkbox -->
                    <th v-if="selectable" class="w-10 border-r border-slate-200 p-2 text-center">
                        <input
                            type="checkbox"
                            :checked="isAllSelected"
                            @click.stop
                            @change="$emit('toggleAll')"
                        />
                    </th>

                    <!-- dynamic columns -->
                    <th 
                        v-for="col in columns" 
                        :key="col.key"
                        class="whitespace-nowrap border-r border-slate-200 px-3 py-2.5"
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

                    <th class="w-16 px-3 py-2.5 text-center">Tác vụ</th>
                </tr>
            </thead>

            <!-- BODY -->
            <tbody>
                <tr v-for="row in data.data"
                    :key="row.id"
                    class="border-t border-slate-100 transition-colors even:bg-slate-50/40 hover:bg-emerald-50/70"
                    :class="rowClickable ? 'cursor-pointer' : ''"
                    :tabindex="rowClickable ? 0 : null"
                    :aria-label="rowClickable ? 'Xem chi tiết' : null"
                    @click="rowClickable && $emit('rowClick', row)"
                    @keydown.enter.self="rowClickable && $emit('rowClick', row)"
                    @keydown.space.self.prevent="rowClickable && $emit('rowClick', row)"
                >
                    <!-- checkbox -->
                    <td v-if="selectable" class="border-r border-slate-100 p-2 text-center">
                        <input
                            type="checkbox"
                            :checked="selectedIds.includes(row.id)"
                            @click.stop
                            @change="$emit('toggleOne', row.id)"
                        />
                    </td>

                    <!-- SLOT render row -->
                    <slot name="row" :row="row" />
                </tr>

                <tr v-if="rows.length === 0">
                    <td :colspan="columns.length + (selectable ? 2 : 1)"
                        class="bg-slate-50 p-10 text-center text-slate-500">
                        Không có dữ liệu
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- PAGINATION -->
    <PaginationBar v-if="!hidePaginationWhenSinglePage || (data?.total || 0) > (filters?.per_page || 10)" :data="data" :filters="filters" />
</div>
</template>
