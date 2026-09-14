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
    'sort',
    'changePerPage'
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
<div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="min-w-[500px] w-full text-sm">

            <!-- HEADER -->
            <thead>
                <tr class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">

                    <!-- checkbox -->
                    <th v-if="selectable" class="w-10 border-r border-slate-200 p-2 text-center">
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
                        class="whitespace-nowrap border-r border-slate-200 px-3 py-2.5 text-left"
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

                    <th class="px-3 py-2.5 text-center">Tác vụ</th>
                </tr>
            </thead>

            <!-- BODY -->
            <tbody>
                <tr v-for="row in data.data"
                    :key="row.id"
                    class="border-t border-slate-100 hover:bg-slate-50"
                >
                    <!-- checkbox -->
                    <td v-if="selectable" class="border-r border-slate-100 p-2 text-center">
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
                        class="bg-slate-50 p-10 text-center text-slate-500">
                        Không có dữ liệu
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- PAGINATION -->
    <div class="flex items-center justify-between border-t border-slate-200 bg-white px-4 py-3 text-sm">
        <div class="w-1/3 text-slate-500">
            Trang {{ data.from }} - {{ data.to }} / {{ data.total }}
        </div>

        <div class="flex justify-center w-full">
            <div class="flex items-center gap-1">
                <button
                    v-for="link in data.links"
                    :key="link.label"
                    v-html="link.label"
                    @click="go(link.url)"
                    class="rounded border px-3 py-1 text-sm"
                    :class="link.active
                        ? 'border-emerald-600 bg-emerald-600 text-white'
                        : 'bg-white hover:bg-slate-100'"
                />
            </div>
        </div>

        <div class="flex justify-end w-1/3">
            <select
                :value="filters?.per_page || 10"
                @change="$emit('changePerPage', Number($event.target.value))"
                class="rounded-lg border border-slate-200 px-6 py-2 text-sm"
            >
                <option :value="10">10</option>
                <option :value="20">20</option>
                <option :value="50">50</option>
            </select>
        </div>
    </div>
</div>
</template>
