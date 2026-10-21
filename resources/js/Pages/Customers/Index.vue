<script setup>
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { Plus, Search, SquarePen } from 'lucide-vue-next'
import { openModal } from '@/Stores/modal'
import { formatMoney } from '@/utils/format'
import PageHeader from '@/Components/UI/PageHeader.vue'
import ActionButton from '@/Components/UI/ActionButton.vue'
import DataPanel from '@/Components/UI/DataPanel.vue'
import CustomerFormModal from './CustomerFormModal.vue'
import BaseTable from '@/Components/UI/BaseTable.vue'

const props = defineProps({
    customers: Object,
    filters: Object,
})

const search = ref(props.filters.search || '')
const columns = [
    { key: 'code', label: 'Mã KH', sortable: true },
    { key: 'full_name', label: 'Họ tên', sortable: true },
    { key: 'phone', label: 'SĐT', sortable: true },
    { key: 'point_balance', label: 'Điểm', sortable: true, class: 'text-center' },
    { key: 'debt_balance', label: 'Công nợ', sortable: true, class: 'text-right' },
    { key: 'customer_type', label: 'Loại', sortable: true, class: 'text-center' },
]
let searchTimeout = null

watch(search, (value) => {
    clearTimeout(searchTimeout)

    searchTimeout = setTimeout(() => {
        router.get(
            route('customers.index'),
            { search: value, sort_by: props.filters?.sort_by, sort_order: props.filters?.sort_order, per_page: props.filters?.per_page },
            {
                preserveState: true,
                replace: true,
            }
        )
    }, 250)
})

const sort = ({ field, order }) => router.get(route('customers.index'), {
    search: search.value, sort_by: field, sort_order: order, per_page: props.filters?.per_page,
}, { preserveState: true, preserveScroll: true, replace: true })

const reload = () => {
    router.reload({
        only: ['customers'],
        preserveScroll: true,
    })
}

const openCreate = () => {
    openModal(CustomerFormModal, {
        props: {
            title: 'Thêm khách hàng',
        },
        onUpdated: reload,
    })
}

const openEdit = (customer) => {
    openModal(CustomerFormModal, {
        props: {
            title: 'Sửa khách hàng',
            customer,
        },
        onUpdated: reload,
    })
}

</script>

<template>
    <div class="space-y-3">
        <PageHeader
            title="Khách hàng"
        >
            <template #actions>
                <ActionButton @click="openCreate">
                    <Plus class="h-4 w-4" />
                    Thêm khách
                </ActionButton>
            </template>
        </PageHeader>

        <DataPanel>
            <template #header>
                <div class="relative max-w-md">
                    <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Tìm tên, SĐT..."
                        class="w-full rounded-lg border border-slate-200 py-2 pl-9 pr-3 text-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                    />
                </div>
            </template>

            <BaseTable :data="customers" :columns="columns" :filters="filters" row-clickable @sort="sort" @row-click="router.visit(`/records/customer/${$event.id}`)">
                <template #row="{ row }">
                    <td class="border-r p-2 font-medium text-slate-700">{{ row.code }}</td>
                    <td class="border-r p-2 font-semibold text-slate-900">{{ row.full_name }}</td>
                    <td class="border-r p-2 text-slate-600">{{ row.phone || '—' }}</td>
                    <td class="border-r p-2 text-center font-semibold text-emerald-700">{{ row.point_balance }}</td>
                    <td class="border-r p-2 text-right font-semibold" :class="row.debt_balance > 0 ? 'text-rose-600' : 'text-slate-500'">{{ formatMoney(row.debt_balance) }}</td>
                    <td class="border-r p-2 text-center text-slate-600">{{ row.customer_type }}</td>
                    <td class="p-2 text-center" @click.stop>
                        <ActionButton variant="ghost" title="Sửa" @click="openEdit(row)"><SquarePen class="h-4 w-4 text-emerald-700" /></ActionButton>
                    </td>
                </template>
            </BaseTable>
        </DataPanel>

    </div>
</template>
