<script setup>
import { computed, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { Printer, Search } from 'lucide-vue-next'
import BaseModal from '@/Components/UI/BaseModal.vue'
import { labelRowsFor, printLabelRows } from '@/utils/printLabels'
import { formatNumber } from '@/utils/format'

const props = defineProps({
    products: { type: Array, default: () => [] },
})
const emit = defineEmits(['close'])
const page = usePage()

const rows = computed(() => labelRowsFor(props.products))
const selected = ref(new Set(rows.value.map(row => row.key)))
const keyword = ref('')

const visibleRows = computed(() => {
    const kw = keyword.value.trim().toLowerCase()
    if (!kw) return rows.value
    return rows.value.filter(row => `${row.productName} ${row.variant} ${row.code}`.toLowerCase().includes(kw))
})

const groups = computed(() => {
    const map = new Map()
    visibleRows.value.forEach(row => {
        if (!map.has(row.productId)) map.set(row.productId, { id: row.productId, name: row.productName, rows: [] })
        map.get(row.productId).rows.push(row)
    })
    return [...map.values()]
})

const selectedRows = computed(() => rows.value.filter(row => selected.value.has(row.key)))
const allVisibleSelected = computed(() => visibleRows.value.length > 0 && visibleRows.value.every(row => selected.value.has(row.key)))

const setMany = (list, checked) => {
    const next = new Set(selected.value)
    list.forEach(row => (checked ? next.add(row.key) : next.delete(row.key)))
    selected.value = next
}
const toggle = (row) => setMany([row], !selected.value.has(row.key))
const groupChecked = (group) => group.rows.every(row => selected.value.has(row.key))

const print = () => {
    if (!selectedRows.value.length) return
    printLabelRows(selectedRows.value, page.props.settings?.label_template, page.props.settings?.shop_name || '')
}
</script>

<template>
    <BaseModal title="In tem IMEI / Serial" size="xl" body-class="p-3 sm:p-4" @close="emit('close')">
        <div class="flex flex-col gap-3">
            <div class="flex flex-wrap items-center gap-2">
                <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                    <input type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600" :checked="allVisibleSelected" @change="setMany(visibleRows, $event.target.checked)" />
                    Chọn tất cả
                </label>
                <span class="text-xs text-slate-500">Đã chọn {{ formatNumber(selectedRows.length) }} / {{ formatNumber(rows.length) }} tem</span>
                <div class="relative ml-auto w-full sm:w-64">
                    <Search class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input v-model="keyword" type="text" placeholder="Lọc theo tên, IMEI/Serial..." class="w-full rounded-lg border border-slate-300 py-1.5 pl-8 pr-2 text-sm outline-none focus:border-blue-500" />
                </div>
            </div>

            <div class="max-h-[55vh] overflow-y-auto rounded-xl border border-slate-200">
                <div v-if="!groups.length" class="px-4 py-10 text-center text-sm text-slate-400">Không có tem nào để in.</div>
                <section v-for="group in groups" :key="group.id" class="border-b border-slate-100 last:border-b-0">
                    <label class="flex cursor-pointer items-center gap-2 bg-slate-50 px-3 py-2 text-sm font-bold text-slate-800">
                        <input type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600" :checked="groupChecked(group)" @change="setMany(group.rows, $event.target.checked)" />
                        <span class="min-w-0 flex-1 truncate">{{ group.name }}</span>
                        <span class="text-xs font-medium text-slate-500">{{ group.rows.length }} tem</span>
                    </label>
                    <label v-for="row in group.rows" :key="row.key" class="flex cursor-pointer items-center gap-3 border-t border-slate-100 px-3 py-2 text-sm hover:bg-slate-50">
                        <input type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600" :checked="selected.has(row.key)" @change="toggle(row)" />
                        <div class="min-w-0 flex-1">
                            <div class="font-mono font-semibold text-slate-900">{{ row.kind }}: {{ row.code }}</div>
                            <div v-if="row.variant" class="truncate text-xs text-slate-500">{{ row.variant }}</div>
                        </div>
                        <div class="shrink-0 font-semibold text-slate-700">{{ formatNumber(row.price || 0) }}</div>
                    </label>
                </section>
            </div>
        </div>

        <template #footer>
            <div class="flex justify-end gap-2">
                <button type="button" class="min-h-10 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="emit('close')">Đóng</button>
                <button type="button" :disabled="!selectedRows.length" class="inline-flex min-h-10 items-center gap-2 rounded-lg bg-blue-600 px-5 py-2 text-sm font-bold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50" @click="print">
                    <Printer class="h-4 w-4" /> In {{ selectedRows.length }} tem đã chọn
                </button>
            </div>
        </template>
    </BaseModal>
</template>
