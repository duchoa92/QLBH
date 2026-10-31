<script setup>
import { computed, ref, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { Plus, Search, SquarePen } from 'lucide-vue-next'
import { openModal, registerUrlModal } from '@/Stores/modal'
import { formatMoney } from '@/utils/format'
import PageHeader from '@/Components/UI/PageHeader.vue'
import ActionButton from '@/Components/UI/ActionButton.vue'
import DataPanel from '@/Components/UI/DataPanel.vue'
import CustomerFormModal from './CustomerFormModal.vue'
import BaseTable from '@/Components/UI/BaseTable.vue'
import RecordDetailModal from '@/Components/RecordDetailModal.vue'
import FloatingInput from '@/Components/UI/FloatingInput.vue'

const props = defineProps({
    customers: Object,
    filters: Object,
})
const page = usePage()
const canViewCustomerDebts = computed(() => page.props.auth?.permissions?.includes('customer_debts.view') ?? false)

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

const openDetail = (customer) => openModal(RecordDetailModal, {
    props: { type: 'customer', recordId: customer.id },
    deepLink: { type: 'customer', id: customer.id },
})
const openDebtDetails = customer => {
    if (!canViewCustomerDebts.value || Number(customer.debt_balance) <= 0) return
    router.visit(route('debts.customers', { open_customer: customer.id }))
}
registerUrlModal('customer', RecordDetailModal, id => ({ type: 'customer', recordId: id }))

const openDetailFromGlobalSearch = () => {
    const url = new URL(window.location.href)
    const customerId = Number(url.searchParams.get('open_customer'))
    if (!customerId) return
    url.searchParams.delete('open_customer')
    router.replace({ url: `${url.pathname}${url.search}${url.hash}`, preserveState: true, preserveScroll: true, onSuccess: () => openDetail({ id: customerId }) })
}

watch(() => page.url, openDetailFromGlobalSearch, { immediate: true })

</script>

<template>
    <div class="space-y-3">
        <PageHeader
            title="Khách hàng"
        >
        </PageHeader>

        <DataPanel detached>
            <template #header>
                <div class="flex w-full items-center gap-2">
                    <div class="relative min-w-0 flex-1 sm:max-w-xl">
                        <Search class="absolute left-3 top-1/2 z-10 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <FloatingInput v-model="search" label="Tìm khách hàng" placeholder-text="Tên, SĐT, mã KH hoặc CCCD" placeholder-class="left-9 right-2 truncate" label-class="left-9" input-class="h-10 !pl-9 !pt-2 !pb-2" />
                    </div>
                    <ActionButton class="shrink-0 whitespace-nowrap" @click="openCreate">
                        <Plus class="h-4 w-4" />
                        Thêm khách
                    </ActionButton>
                </div>
            </template>

            <BaseTable :data="customers" :columns="columns" :filters="filters" row-clickable @sort="sort" @row-click="openDetail">
                <template #row="{ row }">
                    <td class="border-r p-2 font-medium text-slate-700">{{ row.code }}</td>
                    <td class="border-r p-2 font-semibold text-slate-900">{{ row.full_name }}</td>
                    <td class="border-r p-2 text-slate-600">{{ row.phone || '—' }}</td>
                    <td class="border-r p-2 text-center font-semibold text-emerald-700">{{ row.point_balance }}</td>
                    <td class="border-r p-2 text-right font-semibold" :class="[
                        row.debt_balance > 0 ? 'text-rose-600' : 'text-slate-500',
                        canViewCustomerDebts && row.debt_balance > 0 ? 'cursor-pointer underline decoration-dotted underline-offset-2 hover:text-rose-800' : '',
                    ]" @click.stop="openDebtDetails(row)" :title="canViewCustomerDebts && row.debt_balance > 0 ? 'Xem chi tiết công nợ' : undefined">{{ formatMoney(row.debt_balance) }}</td>
                    <td class="border-r p-2 text-center text-slate-600">{{ row.customer_type === 'retail' ? 'Khách lẻ' : row.customer_type }}</td>
                    <td class="w-16 p-2 text-center" @click.stop>
                        <ActionButton variant="ghost" title="Sửa" @click="openEdit(row)"><SquarePen class="h-4 w-4 text-emerald-700" /></ActionButton>
                    </td>
                </template>
            </BaseTable>
        </DataPanel>

    </div>
</template>
