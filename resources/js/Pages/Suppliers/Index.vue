<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { Plus, Search, SquarePen, Trash2 } from 'lucide-vue-next'
import { formatMoney } from '@/utils/format'
import { openModal } from '@/Stores/modal'
import { useConfirm } from '@/Composables/useConfirm'
import PageHeader from '@/Components/UI/PageHeader.vue'
import ActionButton from '@/Components/UI/ActionButton.vue'
import DataPanel from '@/Components/UI/DataPanel.vue'
import SupplierForm from './Form.vue'
import BaseTable from '@/Components/UI/BaseTable.vue'

const props = defineProps({
    suppliers: Object,
    filters: Object,
})

const confirmBox = useConfirm()
const search = ref(props.filters?.search ?? '')
const columns = [
    { key: 'code', label: 'Mã', sortable: true },
    { key: 'name', label: 'Tên', sortable: true },
    { key: 'phone', label: 'Điện thoại', sortable: true },
    { key: 'debt_balance', label: 'Công nợ', sortable: true, class: 'text-right' },
]

let searchTimeout = null

const searchSupplier = () => {
    clearTimeout(searchTimeout)

    searchTimeout = setTimeout(() => {
        router.get(
            route('suppliers.index'),
            { search: search.value, sort_by: props.filters?.sort_by, sort_order: props.filters?.sort_order, per_page: props.filters?.per_page },
            {
                preserveState: true,
                replace: true,
            }
        )
    }, 250)
}

const sort = ({ field, order }) => router.get(route('suppliers.index'), {
    search: search.value, sort_by: field, sort_order: order, per_page: props.filters?.per_page,
}, { preserveState: true, preserveScroll: true, replace: true })

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
        <PageHeader
            title="Nhà cung cấp"
        >
            <template #actions>
                <ActionButton @click="openCreate">
                    <Plus class="h-4 w-4" />
                    Thêm mới
                </ActionButton>
            </template>
        </PageHeader>

        <DataPanel>
            <template #header>
                <div class="relative max-w-md">
                    <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input
                        v-model="search"
                        @input="searchSupplier"
                        type="text"
                        placeholder="Tìm tên, điện thoại, email"
                        class="w-full rounded-lg border border-slate-200 py-2 pl-9 pr-3 text-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                    />
                </div>
            </template>

            <BaseTable :data="suppliers" :columns="columns" :filters="filters" row-clickable @sort="sort" @row-click="router.visit(`/records/supplier/${$event.id}`)">
                <template #row="{ row }">
                    <td class="border-r p-2 font-medium text-slate-700">{{ row.code }}</td>
                    <td class="border-r p-2 font-semibold text-slate-900">{{ row.name }}</td>
                    <td class="border-r p-2 text-slate-600">{{ row.phone || '—' }}</td>
                    <td class="border-r p-2 text-right font-semibold" :class="row.debt_balance > 0 ? 'text-rose-600' : 'text-slate-500'">{{ formatMoney(row.debt_balance) }}</td>
                    <td class="p-2 text-center" @click.stop>
                        <div class="flex justify-center gap-1">
                            <ActionButton variant="ghost" title="Sửa" @click="openEdit(row)"><SquarePen class="h-4 w-4 text-emerald-700" /></ActionButton>
                            <ActionButton variant="ghost" title="Xóa" @click="destroy(row)"><Trash2 class="h-4 w-4 text-rose-600" /></ActionButton>
                        </div>
                    </td>
                </template>
            </BaseTable>
        </DataPanel>

    </div>
</template>
