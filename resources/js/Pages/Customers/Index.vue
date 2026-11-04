<script setup>
import { computed, ref, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { Ban, Plus, RotateCcw, Search, SquarePen, Trash2 } from 'lucide-vue-next'
import { openModal, registerUrlModal } from '@/Stores/modal'
import { formatMoney } from '@/utils/format'
import PageHeader from '@/Components/UI/PageHeader.vue'
import ActionButton from '@/Components/UI/ActionButton.vue'
import DataPanel from '@/Components/UI/DataPanel.vue'
import CustomerFormModal from './CustomerFormModal.vue'
import BaseTable from '@/Components/UI/BaseTable.vue'
import RecordDetailModal from '@/Components/RecordDetailModal.vue'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import { useConfirm } from '@/Composables/useConfirm'
import { toast } from 'vue-sonner'

const props = defineProps({
    customers: Object,
    filters: Object,
})
const page = usePage()
const canViewCustomerDebts = computed(() => page.props.auth?.permissions?.includes('customer_debts.view') ?? false)

const confirmBox = useConfirm()
const permissions = computed(() => page.props.auth?.permissions || [])
const canManageCustomers = computed(() => permissions.value.includes('customers.edit'))

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
                preserveScroll: true,
                replace: true,
                only: ['customers', 'filters'],
            }
        )
    }, 250)
})

const sort = ({ field, order }) => router.get(route('customers.index'), {
    search: search.value, sort_by: field, sort_order: order, per_page: props.filters?.per_page,
}, { preserveState: true, preserveScroll: true, replace: true, only: ['customers', 'filters'] })

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

const firstError = (errors) => Object.values(errors || {}).flat()[0] || 'Không thể thực hiện thao tác.'

const removeCustomer = (customer) => {
    if (customer.has_transactions) return
    confirmBox.show({
        title: 'Xóa khách hàng',
        message: `Xóa khách hàng "${customer.full_name}" (${customer.code})? Chỉ xóa được khi chưa có phát sinh.`,
        confirmText: 'Xóa',
        cancelText: 'Hủy',
        onConfirm: () => new Promise((resolve, reject) => {
            router.delete(route('customers.destroy', customer.id), {
                preserveScroll: true,
                onSuccess: () => { toast.success('Đã xóa khách hàng'); resolve() },
                onError: (errors) => reject(new Error(firstError(errors))),
            })
        }),
    })
}

const toggleBlock = (customer) => {
    const blocking = customer.is_active !== false
    confirmBox.show({
        title: blocking ? 'Chặn khách hàng' : 'Bỏ chặn khách hàng',
        message: blocking
            ? `Chặn "${customer.full_name}"? Khách sẽ không thể phát sinh hóa đơn hoặc phiếu sửa mới. Dữ liệu cũ được giữ nguyên.`
            : `Bỏ chặn "${customer.full_name}" để tiếp tục giao dịch?`,
        confirmText: blocking ? 'Chặn' : 'Bỏ chặn',
        cancelText: 'Hủy',
        onConfirm: () => new Promise((resolve, reject) => {
            router.patch(route('customers.block', customer.id), {}, {
                preserveScroll: true,
                onSuccess: () => { toast.success(blocking ? 'Đã chặn khách hàng' : 'Đã bỏ chặn khách hàng'); resolve() },
                onError: (errors) => reject(new Error(firstError(errors))),
            })
        }),
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
                    <td class="border-r p-2 font-semibold text-slate-900">
                        <span :class="row.is_active === false ? 'text-slate-400 line-through decoration-slate-300' : ''">{{ row.full_name }}</span>
                        <span v-if="row.is_active === false" class="ml-2 inline-flex items-center gap-1 rounded-full border border-rose-200 bg-rose-50 px-2 py-0.5 align-middle text-[10px] font-bold text-rose-700"><Ban class="h-3 w-3" />Đã chặn</span>
                    </td>
                    <td class="border-r p-2 text-slate-600">{{ row.phone || '—' }}</td>
                    <td class="border-r p-2 text-center font-semibold text-emerald-700">{{ row.point_balance }}</td>
                    <td class="border-r p-2 text-right font-semibold" :class="[
                        row.debt_balance > 0 ? 'text-rose-600' : 'text-slate-500',
                        canViewCustomerDebts && row.debt_balance > 0 ? 'cursor-pointer underline decoration-dotted underline-offset-2 hover:text-rose-800' : '',
                    ]" @click.stop="openDebtDetails(row)" :title="canViewCustomerDebts && row.debt_balance > 0 ? 'Xem chi tiết công nợ' : undefined">{{ formatMoney(row.debt_balance) }}</td>
                    <td class="border-r p-2 text-center text-slate-600">{{ row.customer_type === 'retail' ? 'Khách lẻ' : row.customer_type }}</td>
                    <td class="w-32 p-2 text-center" @click.stop>
                        <div class="flex items-center justify-center gap-0.5">
                            <ActionButton variant="ghost" title="Sửa" @click="openEdit(row)"><SquarePen class="h-4 w-4 text-emerald-700" /></ActionButton>
                            <template v-if="canManageCustomers">
                                <ActionButton variant="ghost" :title="row.is_active === false ? 'Bỏ chặn' : 'Chặn giao dịch'" @click="toggleBlock(row)">
                                    <RotateCcw v-if="row.is_active === false" class="h-4 w-4 text-emerald-700" />
                                    <Ban v-else class="h-4 w-4 text-amber-600" />
                                </ActionButton>
                                <ActionButton
                                    variant="ghost"
                                    :disabled="row.has_transactions"
                                    :title="row.has_transactions ? 'Đã có phát sinh, không thể xóa — hãy dùng Chặn' : 'Xóa khách hàng'"
                                    :class="row.has_transactions ? 'cursor-not-allowed opacity-40' : ''"
                                    @click="removeCustomer(row)"
                                ><Trash2 class="h-4 w-4 text-rose-600" /></ActionButton>
                            </template>
                        </div>
                    </td>
                </template>
            </BaseTable>
        </DataPanel>

    </div>
</template>
