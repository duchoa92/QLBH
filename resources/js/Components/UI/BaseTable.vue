<script setup>
import { computed } from 'vue'
import { ArrowUp, ArrowDown, ArrowUpDown, FileText } from 'lucide-vue-next'
import PaginationBar from './PaginationBar.vue'

const props = defineProps({
    data: { type: Object, default: () => ({ data: [] }) },
    columns: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    selectable: { type: Boolean, default: false },
    rowClickable: { type: Boolean, default: false },
    hidePaginationWhenSinglePage: { type: Boolean, default: false },
    selectedIds: { type: Array, default: () => [] },
    hideActions: { type: Boolean, default: false }
})

const emit = defineEmits([
    'toggleOne',
    'toggleAll',
    'sort',
    'rowClick'
])

const rows = computed(() => props.data?.data || [])
const handleSort = (col) => {
    if (!col.sortable) return
    const order = props.filters?.sort_by === col.key && props.filters?.sort_order === 'asc' ? 'desc' : 'asc'
    emit('sort', { field: col.key, order })
}
const headAlign = (col) => /text-right/.test(col.class || '') ? 'justify-end' : /text-center/.test(col.class || '') ? 'justify-center' : ''
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
        <table class="app-data-table w-full min-w-[500px] border-collapse text-left text-xs">

            <!-- HEADER -->
            <thead>
                <tr class="select-none border-b border-slate-200 bg-slate-50/80 text-[11px] font-extrabold uppercase tracking-wider text-slate-500">

                    <th v-if="selectable" class="w-10 px-3.5 py-2.5 text-center">
                        <input
                            type="checkbox"
                            :checked="isAllSelected"
                            @click.stop
                            @change="$emit('toggleAll')"
                        />
                    </th>

                    <th
                        v-for="col in columns"
                        :key="col.key"
                        class="px-3.5 py-2.5 transition"
                        :class="[col.class, col.sortable ? 'cursor-pointer hover:bg-slate-100' : '']"
                        :style="{ width: col.width }"
                        @click="handleSort(col)"
                    >
                        <div v-if="col.sortable" class="flex items-center gap-1" :class="headAlign(col)">
                            <span>{{ col.label }}</span>
                            <template v-if="filters?.sort_by === col.key">
                                <ArrowUp v-if="filters?.sort_order === 'asc'" :size="13" class="text-emerald-700" />
                                <ArrowDown v-else :size="13" class="text-emerald-700" />
                            </template>
                            <ArrowUpDown v-else :size="13" class="text-slate-300" />
                        </div>
                        <span v-else>{{ col.label }}</span>
                    </th>

                    <th v-if="!hideActions" class="w-24 px-2 py-2.5 text-center">Thao Tác</th>
                </tr>
            </thead>

            <!-- BODY -->
            <tbody class="divide-y divide-slate-100 text-xs font-medium">
                <tr v-for="row in rows"
                    :key="row.id"
                    class="transition-colors hover:bg-emerald-50/70"
                    :class="rowClickable ? 'cursor-pointer' : ''"
                    :tabindex="rowClickable ? 0 : null"
                    :aria-label="rowClickable ? 'Xem chi tiết' : null"
                    :title="rowClickable ? 'Bấm vào dòng để xem chi tiết' : null"
                    @click="rowClickable && $emit('rowClick', row)"
                    @keydown.enter.self="rowClickable && $emit('rowClick', row)"
                    @keydown.space.self.prevent="rowClickable && $emit('rowClick', row)"
                >
                    <td v-if="selectable" class="px-3.5 py-2.5 text-center">
                        <input
                            type="checkbox"
                            :checked="selectedIds.includes(row.id)"
                            @click.stop
                            @change="$emit('toggleOne', row.id)"
                        />
                    </td>

                    <slot name="row" :row="row" />
                </tr>

                <tr v-if="rows.length === 0">
                    <td :colspan="columns.length + (selectable ? 1 : 0) + (hideActions ? 0 : 1)" class="px-4 py-12 text-center">
                        <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                            <FileText :size="24" />
                        </div>
                        <p class="text-sm font-bold text-slate-700">Không tìm thấy dữ liệu nào</p>
                        <p class="mt-1 text-xs text-slate-400">Thử thay đổi bộ lọc hoặc từ khóa tìm kiếm</p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="divide-y divide-slate-100 md:hidden">
        <article v-for="row in rows" :key="`mobile-${row.id}`" class="space-y-2.5 p-4 transition active:bg-slate-100" :class="rowClickable ? 'cursor-pointer' : ''" @click="rowClickable && $emit('rowClick', row)">
            <div class="flex items-start gap-2">
                <input v-if="selectable" type="checkbox" class="mt-1 shrink-0" :checked="selectedIds.includes(row.id)" @click.stop @change="$emit('toggleOne', row.id)" />
                <div class="min-w-0 flex-1">
                    <slot name="mobile-title" :row="row">
                    <div class="break-words text-sm font-bold text-slate-900">{{ displayValue(primaryValue(row)) }}</div>
                    </slot>
                    <div v-if="rowClickable" class="mt-0.5 text-[11px] font-semibold text-emerald-600">Xem chi tiết ›</div>
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
        <div v-if="rows.length === 0" class="p-10 text-center">
            <FileText class="mx-auto mb-3 h-9 w-9 text-slate-300" />
            <p class="text-sm font-bold text-slate-700">Không tìm thấy dữ liệu phù hợp</p>
            <p class="mt-1 text-xs text-slate-400">Thử thay đổi từ khóa hoặc điều kiện lọc</p>
        </div>
    </div>

    <!-- PAGINATION -->
    <PaginationBar v-if="!hidePaginationWhenSinglePage || (data?.total || 0) > (filters?.per_page || 10)" :data="data" :filters="filters" />
</div>
</template>
