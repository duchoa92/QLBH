<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { Plus, SquarePen, Trash2 } from 'lucide-vue-next'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import UnitForm from './Form.vue'
import BaseTable from '@/Components/UI/BaseTable.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import PageHeader from '@/Components/UI/PageHeader.vue'
import DataPanel from '@/Components/UI/DataPanel.vue'
import ListToolbar from '@/Components/UI/ListToolbar.vue'
import ActionButton from '@/Components/UI/ActionButton.vue'
import HighlightText from '@/Components/UI/HighlightText.vue'
import CatalogTabs from '@/Components/CatalogTabs.vue'
import RecordDetailModal from '@/Components/RecordDetailModal.vue'
import { useLiveList } from '@/Composables/useLiveList'
import { useConfirm } from '@/Composables/useConfirm'
import { openModal, registerUrlModal } from '@/Stores/modal'

defineOptions({ layout: AdminLayout })

const props = defineProps({
    units: Object,
    filters: Object,
})
const page = usePage()
const confirmBox = useConfirm()

const columns = [
    { key: 'id', label: 'ID', sortable: true, width: '60px' },
    { key: 'name', label: 'Tên', sortable: true },
    { key: 'short_name', label: 'Viết tắt', sortable: true, width: '160px' },
    { key: 'is_active', label: 'Trạng thái', sortable: true, width: '120px', class: 'text-center' },
]

/* FILTER: tìm kiếm / sắp xếp bằng AJAX, không tải lại trang */
const search = ref(props.filters?.search || '')
const list = useLiveList({
    props,
    dataKey: 'units',
    channels: ['units', 'products'],
    params: () => ({ search: search.value.trim() }),
})
const { data: unitsData, filters: filtersData, loading } = list
watch(search, () => list.schedule())
const sort = ({ field, order }) => list.request({ sort_by: field, sort_order: order })

const loadData = () => router.reload({ only: ['units'] })

const openCreate = () => openModal(UnitForm, { props: { title: 'Thêm đơn vị tính', size: 'sm' }, onUpdated: loadData })
const openEdit = (unit) => openModal(UnitForm, { props: { unit, title: 'Sửa đơn vị tính', size: 'sm' }, onUpdated: loadData })

/* STATUS */
const loadingStatus = ref(null)
const toggleStatus = (id) => {
    if (loadingStatus.value) return
    loadingStatus.value = id
    const item = unitsData.value?.data?.find(i => i.id === id)
    if (item) item.is_active = !item.is_active
    router.patch(route('units.toggleStatus', id), {}, {
        preserveScroll: true,
        onError: () => { if (item) item.is_active = !item.is_active },
        onFinish: () => { loadingStatus.value = null },
    })
}

/* DELETE */
const unitDeleteError = computed(() => page.props.errors?.unit || '')
const destroy = (unit) => {
    confirmBox.show({
        title: 'Xác nhận',
        message: `Xóa đơn vị "${unit.name}"? Đơn vị đã dùng trong sản phẩm, phiếu nhập hoặc hóa đơn sẽ không xóa được, khi đó hãy tắt trạng thái.`,
        confirmText: 'Xóa',
        cancelText: 'Hủy',
        onConfirm: () => router.delete(route('units.destroy', unit.id), { preserveScroll: true, onSuccess: loadData }),
    })
}

// Mở nhanh chi tiết đơn vị khi bấm kết quả từ ô tìm kiếm chung (?open_unit=...).
const openFromGlobalSearch = () => {
    const url = new URL(window.location.href)
    const unitId = Number(url.searchParams.get('open_unit'))
    if (!unitId) return
    url.searchParams.delete('open_unit')
    router.replace({
        url: `${url.pathname}${url.search}${url.hash}`,
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => openModal(RecordDetailModal, { props: { type: 'unit', recordId: unitId }, deepLink: { type: 'unit', id: unitId } }),
    })
}

registerUrlModal('unit', RecordDetailModal, id => ({ type: 'unit', recordId: id }))
watch(() => page.url, () => nextTick(openFromGlobalSearch), { immediate: true })
</script>

<template>
<div class="space-y-3">
    <PageHeader title="Danh mục, thương hiệu & đơn vị tính" />

    <CatalogTabs>
        <ActionButton @click="openCreate"><Plus :size="16" />Thêm</ActionButton>
    </CatalogTabs>

    <div v-if="unitDeleteError" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700" role="alert">
        {{ unitDeleteError }}
    </div>

    <DataPanel detached>
        <template #header>
            <ListToolbar v-model="search" :loading="loading" placeholder="Tìm đơn vị tính..." />
        </template>

        <BaseTable
            :data="unitsData"
            :columns="columns"
            :filters="filtersData"
            row-clickable
            @sort="sort"
            @row-click="openEdit($event)"
        >
            <template #row="{ row }">
                <td class="px-3.5 py-2.5 text-center">{{ row.id }}</td>

                <td class="px-3.5 py-2.5 font-medium">
                    <HighlightText :text="row.name" :keyword="search" />
                </td>

                <td class="px-3.5 py-2.5 text-slate-600">
                    <HighlightText v-if="row.short_name" :text="row.short_name" :keyword="search" />
                    <span v-else class="text-slate-400">---</span>
                </td>

                <td class="px-3.5 py-2.5 text-center">
                    <button
                        @click.stop="toggleStatus(row.id)"
                        :disabled="loadingStatus === row.id"
                        role="switch"
                        :aria-checked="!!row.is_active"
                        aria-label="Bật/tắt trạng thái"
                        class="relative inline-flex h-6 w-11 items-center rounded-full transition"
                        :class="row.is_active ? 'bg-green-500' : 'bg-gray-300'"
                    >
                        <span class="inline-block h-4 w-4 transform rounded-full bg-white transition" :class="row.is_active ? 'translate-x-6' : 'translate-x-1'" />
                    </button>
                </td>

                <td class="px-3.5 py-2.5 text-center" @click.stop>
                    <div class="app-row-actions">
                        <Tooltip text="Sửa">
                            <button type="button" @click="openEdit(row)" class="app-row-action app-row-action--edit">
                                <SquarePen class="h-4 w-4" />
                            </button>
                        </Tooltip>

                        <Tooltip text="Xóa" position="top">
                            <button type="button" @click="destroy(row)" class="app-row-action app-row-action--danger">
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </Tooltip>
                    </div>
                </td>
            </template>

            <template #mobile-body="{ row }">
                <div>
                    <div class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Viết tắt</div>
                    <div class="mt-1 text-xs text-slate-700">{{ row.short_name || 'Chưa có' }}</div>
                </div>
            </template>

            <template #mobile-title="{ row }">
                <div class="flex min-w-0 items-center justify-between gap-3">
                    <span class="min-w-0 break-words text-sm font-bold text-slate-900"><HighlightText :text="row.name" :keyword="search" /></span>
                    <div class="flex shrink-0 items-center gap-1" @click.stop>
                        <button
                            type="button"
                            @click="toggleStatus(row.id)"
                            :disabled="loadingStatus === row.id"
                            role="switch"
                            :aria-checked="!!row.is_active"
                            :aria-label="row.is_active ? 'Ngừng đơn vị' : 'Kích hoạt đơn vị'"
                            class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition"
                            :class="row.is_active ? 'bg-green-500' : 'bg-gray-300'"
                        >
                            <span class="inline-block h-4 w-4 transform rounded-full bg-white transition" :class="row.is_active ? 'translate-x-6' : 'translate-x-1'" />
                        </button>
                        <button type="button" @click="openEdit(row)" class="app-row-action app-row-action--edit" title="Sửa" aria-label="Sửa"><SquarePen class="h-5 w-5" /></button>
                        <button type="button" @click="destroy(row)" class="app-row-action app-row-action--danger" title="Xóa" aria-label="Xóa"><Trash2 class="h-5 w-5" /></button>
                    </div>
                </div>
            </template>
        </BaseTable>
    </DataPanel>
</div>
</template>
