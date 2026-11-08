<script setup>
import { ref, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { Plus, SquarePen, Trash2 } from 'lucide-vue-next'
import { formatMoney } from '@/utils/format'
import { openModal, registerUrlModal } from '@/Stores/modal'
import { useConfirm } from '@/Composables/useConfirm'
import PageHeader from '@/Components/UI/PageHeader.vue'
import ActionButton from '@/Components/UI/ActionButton.vue'
import DataPanel from '@/Components/UI/DataPanel.vue'
import ListToolbar from '@/Components/UI/ListToolbar.vue'
import ListFilterSelect from '@/Components/UI/ListFilterSelect.vue'
import DateRangeFilter from '@/Components/UI/DateRangeFilter.vue'
import HighlightText from '@/Components/UI/HighlightText.vue'
import { useLiveList } from '@/Composables/useLiveList'
import SupplierForm from './Form.vue'
import BaseTable from '@/Components/UI/BaseTable.vue'
import RecordDetailModal from '@/Components/RecordDetailModal.vue'

const props = defineProps({
    suppliers: Object,
    filters: Object,
})
const page = usePage()

const confirmBox = useConfirm()
const search = ref(props.filters?.search ?? '')
const debt = ref(props.filters?.debt ?? '')
const dateFrom = ref(props.filters?.date_from ?? '')
const dateTo = ref(props.filters?.date_to ?? '')
const debtOptions = [
    { value: '', label: 'Tất cả công nợ' },
    { value: 'owing', label: 'Đang còn nợ' },
    { value: 'clear', label: 'Không còn nợ' },
]
const columns = [
    { key: 'code', label: 'Mã', sortable: true },
    { key: 'name', label: 'Tên', sortable: true },
    { key: 'phone', label: 'Điện thoại', sortable: true },
    { key: 'debt_balance', label: 'Công nợ', sortable: true, class: 'text-right' },
]

// Tìm kiếm/sắp xếp bằng AJAX, không tải lại trang; kết quả được bôi vàng.
const list = useLiveList({
    props,
    dataKey: 'suppliers',
    params: () => ({ search: search.value.trim(), debt: debt.value, date_from: dateFrom.value, date_to: dateTo.value }),
})
const { data: suppliersData, filters: filtersData, loading } = list
watch(search, () => list.schedule())
watch([debt, dateFrom, dateTo], () => list.request())
const sort = ({ field, order }) => list.request({ sort_by: field, sort_order: order })

const reload = () => {
    router.reload({
        only: ['suppliers'],
        preserveScroll: true,
    })
}

const openCreate = () => {
    openModal(SupplierForm, {
        props: {
            title: 'Thêm nhà cung cấp',
        },
        onUpdated: reload,
    })
}

const openEdit = (supplier) => {
    openModal(SupplierForm, {
        props: {
            title: 'Sửa nhà cung cấp',
            supplier,
        },
        onUpdated: reload,
    })
}

const openDetail = (supplier) => openModal(RecordDetailModal, {
    props: { type: 'supplier', recordId: supplier.id },
    deepLink: { type: 'supplier', id: supplier.id },
})
registerUrlModal('supplier', RecordDetailModal, id => ({ type: 'supplier', recordId: id }))

const openDetailFromGlobalSearch = () => {
    const url = new URL(window.location.href)
    const supplierId = Number(url.searchParams.get('open_supplier'))
    if (!supplierId) return
    url.searchParams.delete('open_supplier')
    router.replace({ url: `${url.pathname}${url.search}${url.hash}`, preserveState: true, preserveScroll: true, onSuccess: () => openDetail({ id: supplierId }) })
}

watch(() => page.url, openDetailFromGlobalSearch, { immediate: true })

const destroy = (supplier) => {
    confirmBox.show({
        title: 'Xác nhận xóa',
        message: `Xóa nhà cung cấp "${supplier.name}"?`,
        confirmText: 'Xóa',
        cancelText: 'Hủy',
        onConfirm: () => {
            router.delete(route('suppliers.destroy', supplier.id), {
                preserveScroll: true,
                onSuccess: reload,
            })
        },
    })
}

</script>

<template>
    <div class="space-y-3">
        <PageHeader title="Nhà cung cấp" />

        <DataPanel detached>
            <template #header>
                <ListToolbar v-model="search" :loading="loading" placeholder="Tìm tên, điện thoại hoặc email nhà cung cấp..." @submit="list.request()">
                    <template #filters>
                        <ListFilterSelect v-model="debt" label="Công nợ" :show-placeholder="false" :options="debtOptions" />
                        <DateRangeFilter v-model:from="dateFrom" v-model:to="dateTo" />
                    </template>
                    <template #actions><ActionButton @click="openCreate"><Plus class="h-4 w-4" />Thêm mới</ActionButton></template>
                </ListToolbar>
            </template>

            <BaseTable :data="suppliersData" :columns="columns" :filters="filtersData" row-clickable @sort="sort" @row-click="openDetail">
                <template #row="{ row }">
                    <td class="px-3.5 py-2.5 font-medium text-slate-700"><HighlightText :text="row.code" :keyword="search" /></td>
                    <td class="px-3.5 py-2.5 font-semibold text-slate-900"><HighlightText :text="row.name" :keyword="search" /></td>
                    <td class="px-3.5 py-2.5 text-slate-600"><HighlightText v-if="row.phone" :text="row.phone" :keyword="search" /><template v-else>—</template></td>
                    <td class="px-3.5 py-2.5 text-right font-semibold" :class="row.debt_balance > 0 ? 'text-rose-600' : 'text-slate-500'">{{ formatMoney(row.debt_balance) }}</td>
                    <td class="px-3.5 py-2.5 text-center" @click.stop>
                        <div class="app-row-actions">
                            <button type="button" title="Sửa" @click="openEdit(row)" class="app-row-action app-row-action--edit"><SquarePen class="h-4 w-4" /></button>
                            <button type="button" title="Xóa" @click="destroy(row)" class="app-row-action app-row-action--danger"><Trash2 class="h-4 w-4" /></button>
                        </div>
                    </td>
                </template>
            </BaseTable>
        </DataPanel>

    </div>
</template>
