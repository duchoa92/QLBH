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
const displayValue = (value, key = '') => {
    if (value === null || value === undefined || value === '') return '—'
    if (typeof value === 'boolean') return key === 'is_active' ? (value ? 'Hoạt động' : 'Ngừng') : (value ? 'Có' : 'Không')
    if (Array.isArray(value)) return value.map(item => typeof item === 'object' ? (item.name || item.label || '') : item).filter(Boolean).join(', ') || '—'
    if (typeof value === 'object') return value.name || value.full_name || value.label || value.code || '—'
    if (typeof value === 'number' && /price|amount|total|balance|debt/i.test(key)) return `${value.toLocaleString('vi-VN')} đ`
    if (typeof value === 'number' && (key === 'is_active' || key === 'status')) return value ? 'Hoạt động' : 'Ngừng'
    return String(value)
}
const primaryValue = row => row?.name || row?.full_name || row?.description || row?.code || row?.[props.columns[0]?.key]

</script>

<template>
<div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="hidden overflow-x-auto md:block">
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

    <div class="divide-y divide-slate-200 md:hidden">
        <article v-for="row in rows" :key="`mobile-${row.id}`" class="space-y-2.5 p-3.5 transition active:bg-slate-50" :class="rowClickable ? 'cursor-pointer' : ''" @click="rowClickable && $emit('rowClick', row)">
            <div class="flex items-start gap-2">
                <input v-if="selectable" type="checkbox" class="mt-1 shrink-0" :checked="selectedIds.includes(row.id)" @click.stop @change="$emit('toggleOne', row.id)" />
                <div class="min-w-0 flex-1">
                    <slot name="mobile-title" :row="row">
                    <div class="break-words text-sm font-bold text-slate-900">{{ displayValue(primaryValue(row)) }}</div>
                    </slot>
                    <div v-if="rowClickable" class="mt-0.5 text-[11px] font-semibold text-emerald-700">Xem chi tiết ›</div>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-x-3 gap-y-2 pl-6 text-xs">
                <template v-for="col in columns.slice(1)" :key="col.key">
                    <div class="min-w-0">
                        <div class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">{{ col.label }}</div>
                        <div class="mt-0.5 break-words font-medium text-slate-700" :class="col.class">{{ displayValue(row[col.key], col.key) }}</div>
                    </div>
                </template>
            </div>
            <slot name="mobile-actions" :row="row" />
        </article>
        <div v-if="rows.length === 0" class="p-8 text-center text-sm text-slate-500">Không có dữ liệu</div>
    </div>

    <!-- PAGINATION -->
    <PaginationBar v-if="!hidePaginationWhenSinglePage || (data?.total || 0) > (filters?.per_page || 10)" :data="data" :filters="filters" />
</div>
</template>
