<script setup>
import { useSilentRefresh } from '@/Composables/useSilentRefresh'
import { computed, ref, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { Ban, Plus, RotateCcw, SquarePen, Trash2 } from 'lucide-vue-next'
import { openModal, registerUrlModal } from '@/Stores/modal'
import { formatMoney } from '@/utils/format'
import PageHeader from '@/Components/UI/PageHeader.vue'
import ActionButton from '@/Components/UI/ActionButton.vue'
import DataPanel from '@/Components/UI/DataPanel.vue'
import ListToolbar from '@/Components/UI/ListToolbar.vue'
import ListFilterSelect from '@/Components/UI/ListFilterSelect.vue'
import DateRangeFilter from '@/Components/UI/DateRangeFilter.vue'
import HighlightText from '@/Components/UI/HighlightText.vue'
import { useLiveList } from '@/Composables/useLiveList'
import CustomerFormModal from './CustomerFormModal.vue'
import BaseTable from '@/Components/UI/BaseTable.vue'
import CustomerDetailModal from './CustomerDetailModal.vue'
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
const debt = ref(props.filters.debt || '')
const status = ref(props.filters.status || '')
const points = ref(props.filters.points || '')
const dateFrom = ref(props.filters.date_from || '')
const dateTo = ref(props.filters.date_to || '')
const debtOptions = [
    { value: '', label: 'Tất cả công nợ' },
    { value: 'owing', label: 'Đang còn nợ' },
    { value: 'clear', label: 'Không còn nợ' },
]
const statusOptions = [
    { value: '', label: 'Mọi trạng thái' },
    { value: 'active', label: 'Đang giao dịch' },
    { value: 'blocked', label: 'Đã chặn' },
]
const pointOptions = [
    { value: '', label: 'Tất cả điểm' },
    { value: 'has', label: 'Có điểm tích lũy' },
    { value: 'none', label: 'Chưa có điểm' },
]
const columns = [
    { key: 'code', label: 'Mã KH', sortable: true },
    { key: 'full_name', label: 'Họ tên', sortable: true },
    { key: 'phone', label: 'SĐT', sortable: true },
    { key: 'point_balance', label: 'Điểm', sortable: true, class: 'text-center' },
    { key: 'debt_balance', label: 'Công nợ', sortable: true, class: 'text-right' },
    { key: 'customer_type', label: 'Loại', sortable: true, class: 'text-center' },
]
// Tìm kiếm/sắp xếp bằng AJAX, không tải lại trang; kết quả được bôi vàng.
const list = useLiveList({
    props,
    dataKey: 'customers',
    params: () => ({
        search: search.value.trim(),
        debt: debt.value,
        status: status.value,
        points: points.value,
        date_from: dateFrom.value,
        date_to: dateTo.value,
    }),
})
const { data: customersData, filters: filtersData, loading } = list
watch(search, () => list.schedule())
watch([debt, status, points, dateFrom, dateTo], () => list.request())
const sort = ({ field, order }) => list.request({ sort_by: field, sort_order: order })

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

const openDetail = (customer) => openModal(CustomerDetailModal, {
    props: { recordId: customer.id },
    deepLink: { type: 'customer', id: customer.id },
})
const openDebtDetails = customer => {
    if (!canViewCustomerDebts.value || Number(customer.debt_balance) <= 0) return
    router.visit(route('debts.customers', { open_customer: customer.id }))
}
registerUrlModal('customer', CustomerDetailModal, id => ({ recordId: id }))

const openDetailFromGlobalSearch = () => {
    const url = new URL(window.location.href)
    const customerId = Number(url.searchParams.get('open_customer'))
    if (!customerId) return
    url.searchParams.delete('open_customer')
    router.replace({ url: `${url.pathname}${url.search}${url.hash}`, preserveState: true, preserveScroll: true, onSuccess: () => openDetail({ id: customerId }) })
}

watch(() => page.url, openDetailFromGlobalSearch, { immediate: true })


// Quay lại tab thì nạp ngầm danh sách mới nhất (không tải lại trang, không làm gián đoạn khi đang mở form).
useSilentRefresh(['customers'])
</script>

<template>
    <div class="space-y-3">
        <PageHeader title="Khách hàng" />

        <DataPanel detached>
            <template #header>
                <ListToolbar v-model="search" :loading="loading" placeholder="Tìm tên, SĐT, mã khách hàng hoặc CCCD..." @submit="list.request()">
                    <template #filters>
                        <ListFilterSelect v-model="debt" label="Công nợ" :show-placeholder="false" :options="debtOptions" />
                        <ListFilterSelect v-model="status" label="Trạng thái" :show-placeholder="false" :options="statusOptions" />
                        <ListFilterSelect v-model="points" label="Điểm tích lũy" :show-placeholder="false" :options="pointOptions" />
                        <DateRangeFilter v-model:from="dateFrom" v-model:to="dateTo" />
                    </template>
                    <template #actions><ActionButton class="shrink-0 whitespace-nowrap" @click="openCreate">
                        <Plus class="h-4 w-4" />
                        Thêm khách
                    </ActionButton></template>
                </ListToolbar>
            </template>

            <BaseTable :data="customersData" :columns="columns" :filters="filtersData" row-clickable @sort="sort" @row-click="openDetail">
                <template #row="{ row }">
                    <td class="px-3.5 py-2.5 font-medium text-slate-700"><HighlightText :text="row.code" :keyword="search" /></td>
                    <td class="px-3.5 py-2.5 font-semibold text-slate-900">
                        <span :class="row.is_active === false ? 'text-slate-400 line-through decoration-slate-300' : ''"><HighlightText :text="row.full_name" :keyword="search" /></span>
                        <span v-if="row.is_active === false" class="ml-2 inline-flex items-center gap-1 rounded-full border border-rose-200 bg-rose-50 px-2 py-0.5 align-middle text-[10px] font-bold text-rose-700"><Ban class="h-3 w-3" />Đã chặn</span>
                    </td>
                    <td class="px-3.5 py-2.5 text-slate-600"><HighlightText v-if="row.phone" :text="row.phone" :keyword="search" /><template v-else>—</template></td>
                    <td class="px-3.5 py-2.5 text-center font-semibold text-emerald-700">{{ row.point_balance }}</td>
                    <td class="px-3.5 py-2.5 text-right font-semibold" :class="[
                        row.debt_balance > 0 ? 'text-rose-600' : 'text-slate-500',
                        canViewCustomerDebts && row.debt_balance > 0 ? 'cursor-pointer underline decoration-dotted underline-offset-2 hover:text-rose-800' : '',
                    ]" @click.stop="openDebtDetails(row)" :title="canViewCustomerDebts && row.debt_balance > 0 ? 'Xem chi tiết công nợ' : undefined">{{ formatMoney(row.debt_balance) }}</td>
                    <td class="px-3.5 py-2.5 text-center text-slate-600">{{ row.customer_type === 'retail' ? 'Khách lẻ' : row.customer_type }}</td>
                    <td class="px-3.5 py-2.5 text-center" @click.stop>
                        <div class="app-row-actions">
                            <button type="button" title="Sửa" @click="openEdit(row)" class="app-row-action app-row-action--edit"><SquarePen class="h-4 w-4" /></button>
                            <template v-if="canManageCustomers">
                                <button type="button" class="app-row-action" :class="row.is_active === false ? 'app-row-action--view' : 'app-row-action--edit'" :title="row.is_active === false ? 'Bỏ chặn' : 'Chặn giao dịch'" @click="toggleBlock(row)">
                                    <RotateCcw v-if="row.is_active === false" class="h-4 w-4" />
                                    <Ban v-else class="h-4 w-4" />
                                </button>
                                <button type="button"
                                   
                                    :disabled="row.has_transactions"
                                    :title="row.has_transactions ? 'Đã có phát sinh, không thể xóa — hãy dùng Chặn' : 'Xóa khách hàng'"
                                    :class="row.has_transactions ? 'cursor-not-allowed opacity-40' : ''"
                                    @click="removeCustomer(row)"
                                 class="app-row-action app-row-action--danger"><Trash2 class="h-4 w-4" /></button>
                            </template>
                        </div>
                    </td>
                </template>
            </BaseTable>
        </DataPanel>

    </div>
</template>
