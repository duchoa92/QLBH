<!-- resources/js/Pages/Pos/InvoiceList.vue -->
<script setup>
import { onMounted, ref, computed, onBeforeUnmount } from 'vue'
import axios from 'axios'
import { usePage } from '@inertiajs/vue3'
import { Pencil, Undo2, X, CircleCheck, CircleX, Clock3, Copy, Printer, ChevronLeft, ChevronRight, Banknote, CreditCard } from 'lucide-vue-next'
import InvoiceDetailModal from '@/Components/InvoiceDetailModal.vue'
import SaleEditModal from '@/Components/SaleEditModal.vue'
import BaseModal from '@/Components/UI/BaseModal.vue'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import FloatingSelect from '@/Components/UI/FloatingSelect.vue'
import PrintPreviewModal from '@/Components/PrintPreviewModal.vue'
import { useSaleHistory } from '../Composables/useSaleHistory'

const {
    invoices,
    selectedInvoice,
    showDetail,
    currentPage,
    lastPage,
    total,
    from,
    to,
    filters,
    loadInvoices,
    fetchInvoice,
    openInvoice,
} = useSaleHistory()

const page = usePage()
const canEditSales = computed(() => page.props.auth?.permissions?.includes('sales.edit') ?? false)
const canCancelSales = computed(() => page.props.auth?.permissions?.includes('sales.cancel') ?? false)
const editSale = ref(null)
const showCancelModal = ref(false)
const saleToCancel = ref(null)
const cancelReason = ref('')
const cancelError = ref('')
const printPreviewUrl = ref('')
const datePreset = ref('all')
let searchTimer

const visiblePages = computed(() => {
    const start = Math.max(1, Math.min(currentPage.value - 1, lastPage.value - 2))
    return Array.from({ length: Math.min(3, lastPage.value) }, (_, index) => start + index)
})

const localDateKey = (date) => {
    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')
    return `${year}-${month}-${day}`
}

const setDatePreset = (preset) => {
    datePreset.value = preset
    const now = new Date()
    if (preset === 'today') {
        filters.value.date_from = localDateKey(now)
        filters.value.date_to = localDateKey(now)
    } else if (preset === 'month') {
        filters.value.date_from = localDateKey(new Date(now.getFullYear(), now.getMonth(), 1))
        filters.value.date_to = localDateKey(now)
    } else if (preset === 'all') {
        filters.value.date_from = ''
        filters.value.date_to = ''
    }
    if (preset !== 'custom') loadInvoices(1)
}

const setStatus = (status) => {
    filters.value.status = status
    loadInvoices(1)
}

const searchInvoices = () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => loadInvoices(1), 300)
}

const onCustomDateChange = () => {
    datePreset.value = 'custom'
    loadInvoices(1)
}

const statusOptions = [
    { value: 'completed', label: 'Thành công' },
    { value: 'cancelled', label: 'Đã hủy' },
    { value: 'pending', label: 'Đang chờ xử lý' },
]
const dateOptions = [
    { value: 'all', label: 'Mọi thời gian' },
    { value: 'today', label: 'Hôm nay' },
    { value: 'month', label: 'Tháng này' },
    { value: 'custom', label: 'Tùy chỉnh' },
]

const copyCode = async (code) => {
    try {
        await navigator.clipboard.writeText(String(code || ''))
    } catch (error) {
        console.error('Không thể sao chép mã hóa đơn', error)
    }
}

const paymentLabel = (method) => ({
    cash: 'Tiền mặt', bank: 'Chuyển khoản', mixed: 'Thanh toán hỗn hợp',
    card: 'Thẻ', qr: 'QR', transfer: 'Chuyển khoản',
}[String(method || '').toLowerCase()] || method || 'Tiền mặt')
const invoicePaidAmount = (invoice) => Math.max(0, Number(invoice?.paid_amount || 0) - Number(invoice?.old_debt_paid || 0))
const invoiceHasDebt = (invoice) => invoice?.status !== 'cancelled'
    && Number(invoice?.grand_total || 0) - invoicePaidAmount(invoice) > 0.009

onMounted(() => {
    loadInvoices()
})

onBeforeUnmount(() => clearTimeout(searchTimer))

const openPrintWindow = (invoice) => {
    if (!invoice?.id) return
    printPreviewUrl.value = route('sales.receipt', invoice.id)
}

const openEdit = async (invoice) => {
    showDetail.value = false
    const detail = await fetchInvoice(invoice.id)
    if (detail) {
        selectedInvoice.value = detail
        editSale.value = detail
    }
}

const confirmCancel = (invoice) => {
    saleToCancel.value = invoice
    cancelReason.value = ''
    cancelError.value = ''
    showCancelModal.value = true
}

const cancelInvoice = async () => {
    if (!saleToCancel.value) return
    cancelError.value = ''
    try {
        await axios.post(route('sales.cancel', saleToCancel.value.id), {
            reason: cancelReason.value.trim(),
            cancel_reason: cancelReason.value.trim(),
        })
        showCancelModal.value = false
        showDetail.value = false
        saleToCancel.value = null
        await loadInvoices(currentPage.value)
    } catch (error) {
        cancelError.value = error.response?.data?.message || 'Không thể hủy hóa đơn. Vui lòng thử lại.'
    }
}

const refreshInvoices = () => {
    editSale.value = null
    showDetail.value = false
    loadInvoices(currentPage.value)
}
</script>

<template>
    <div class="flex h-full min-h-0 flex-col">
        <div class="shrink-0 border-b border-slate-200 px-3 py-3 sm:px-4">
        <div class="flex flex-wrap items-center gap-2">
                <div class="relative min-w-[220px] flex-1">
                    <FloatingInput v-model="filters.search" label="Mã HĐ/Khách hàng" type="search" placeholder-text="Nhập mã HĐ hoặc tên khách hàng" input-class="!h-10 !rounded-lg !border-slate-300 !pl-3 !pr-9 focus:!border-blue-500" @input="searchInvoices" />
                    <button v-if="filters.search" type="button" class="absolute right-3 top-3 rounded p-1 text-slate-400 hover:bg-slate-100" aria-label="Xóa tìm kiếm" @click="filters.search = ''; searchInvoices()"><X class="h-4 w-4" /></button>
                </div>
                <div class="min-w-[150px]">
                    <FloatingSelect :model-value="filters.status" label="Trạng thái" placeholder-text="Tất cả" :options="statusOptions" @update:model-value="setStatus($event || '')" />
                </div>
                <div class="min-w-[155px]">
                    <FloatingSelect :model-value="datePreset" label="Thời gian" :show-placeholder="false" :options="dateOptions" @update:model-value="setDatePreset" />
                </div>
                <div v-if="datePreset === 'custom'" class="flex min-w-[260px] flex-1 items-center gap-2">
                    <FloatingInput v-model="filters.date_from" label="Từ ngày" type="date" input-class="!h-10 !rounded-lg !border-slate-300 focus:!border-blue-500" @change="onCustomDateChange" />
                    <FloatingInput v-model="filters.date_to" label="Đến ngày" type="date" input-class="!h-10 !rounded-lg !border-slate-300 focus:!border-blue-500" @change="onCustomDateChange" />
                </div>
        </div>

        </div>

        <div class="min-h-0 flex-1 overflow-auto px-3 sm:px-4">
        <div class="overflow-x-auto">
        <table class="w-full min-w-[640px] table-fixed text-sm">
            <colgroup>
                <col class="w-[20%]" /><col class="w-[20%]" /><col class="w-[16%]" /><col class="w-[18%]" /><col class="w-[14%]" /><col class="w-[12%]" />
            </colgroup>
            <thead class="sticky top-0 z-10 bg-slate-100 text-slate-600 shadow-[0_1px_0_0_#e2e8f0]">
                <tr class="text-center">
                    <th class="p-2 text-left">Mã HĐ</th>
                    <th class="p-2">Khách hàng</th>
                    <th class="p-2">Trạng thái</th>
                    <th class="p-2">Thanh toán</th>
                    <th class="p-2 text-right">Tổng tiền</th>
                    <th class="w-24 p-2 text-center">Thao tác</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <tr
                    v-for="invoice in invoices"
                    :key="invoice.id"
                    class="cursor-pointer text-[13px] leading-4 hover:bg-blue-50"
                    :class="{ 'bg-red-50/40': invoice.status === 'cancelled' }"
                    title="Bấm vào hóa đơn để xem chi tiết"
                    @click="openInvoice(invoice.id)"
                >
                    <td class="px-2 py-1 text-blue-700 font-bold">
                        <div class="group inline-flex max-w-full min-w-0 items-center gap-1">
                            <span class="block min-w-0 truncate" :title="`#${invoice.code}`">#{{ invoice.code }}</span>
                            <button type="button" class="shrink-0 rounded p-1 text-slate-400 opacity-100 hover:bg-blue-100 hover:text-blue-700 sm:opacity-0 sm:group-hover:opacity-100" title="Sao chép mã hóa đơn" aria-label="Sao chép mã hóa đơn" @click.stop="copyCode(invoice.code)"><Copy class="h-3.5 w-3.5" /></button>
                        </div>
                    </td>
                    <td class="px-2 py-1 text-center"><span class="block truncate" :title="invoice.customer?.full_name ?? 'Khách lẻ'">{{ invoice.customer?.full_name ?? 'Khách lẻ' }}</span></td>
                    <td class="px-2 py-1 text-center">
                        <span
                            v-if="invoice.status === 'cancelled'"
                            class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2 py-0.5 text-xs font-bold text-red-700"
                        >
                            <CircleX class="h-3.5 w-3.5" />
                            Đã hủy
                        </span>
                        <span
                            v-else-if="invoice.status === 'pending' || invoice.status === 'waiting'"
                            class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-bold text-amber-800"
                        >
                            <Clock3 class="h-3.5 w-3.5" />
                            Đang chờ xử lý
                        </span>
                        <span v-else class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-bold text-emerald-700">
                            <CircleCheck class="h-3.5 w-3.5" />
                            Thành công
                        </span>
                    </td>
                    <td class="px-2 py-1 text-center"><span class="inline-flex items-center gap-1 text-xs text-slate-600"><Banknote v-if="['cash','mixed',''].includes(String(invoice.payment_method || '').toLowerCase())" class="h-3.5 w-3.5 text-emerald-600" /><CreditCard v-else class="h-3.5 w-3.5 text-blue-600" />{{ paymentLabel(invoice.payment_method) }}</span></td>
                    <td class="whitespace-nowrap px-2 py-1 text-right font-semibold tabular-nums" :class="invoiceHasDebt(invoice) ? 'text-amber-700' : 'text-slate-800'">{{ $money(invoice.grand_total) }}</td>
                    <td class="px-2 py-1 text-center" @click.stop>
                        <div class="inline-flex items-center gap-1">
                            <button type="button" class="rounded-md p-1 text-slate-600 hover:bg-slate-100" title="In lại hóa đơn" aria-label="In lại hóa đơn" @click="openPrintWindow(invoice)">
                                <Printer class="h-4 w-4" />
                            </button>
                            <button v-if="canEditSales" type="button" class="rounded-md p-1 text-amber-600 hover:bg-amber-50" title="Sửa hóa đơn" aria-label="Sửa hóa đơn" @click="openEdit(invoice)">
                                <Pencil class="h-4 w-4" />
                            </button>
                            <button v-if="canCancelSales && invoice.status !== 'cancelled'" type="button" class="rounded-md p-1 text-rose-600 hover:bg-rose-50" title="Hủy hóa đơn và hoàn kho" aria-label="Hủy hóa đơn và hoàn kho" @click="confirmCancel(invoice)">
                                <Undo2 class="h-4 w-4" />
                            </button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
        </div>
        <div v-if="!invoices.length" class="p-6 text-center text-sm text-slate-500">Không tìm thấy hóa đơn phù hợp.</div>
        </div>
        <div class="flex shrink-0 flex-wrap items-center justify-between gap-3 border-t border-slate-200 bg-slate-50 px-3 py-2.5 text-xs text-slate-600">
            <div>Hiển thị {{ from }}-{{ to }} của {{ total }} kết quả</div>
            <div class="flex items-center gap-1">
                <button type="button" class="rounded-md border border-slate-300 bg-white p-1.5 disabled:opacity-40" aria-label="Trang trước" :disabled="currentPage <= 1" @click="loadInvoices(currentPage - 1)"><ChevronLeft class="h-4 w-4" /></button>
                <button v-for="pageNumber in visiblePages" :key="pageNumber" type="button" class="min-w-8 rounded-md border px-2 py-1.5 font-semibold" :class="currentPage === pageNumber ? 'border-blue-600 bg-blue-600 text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-100'" :aria-current="currentPage === pageNumber ? 'page' : undefined" @click="loadInvoices(pageNumber)">{{ pageNumber }}</button>
                <button type="button" class="rounded-md border border-slate-300 bg-white p-1.5 disabled:opacity-40" aria-label="Trang sau" :disabled="currentPage >= lastPage" @click="loadInvoices(currentPage + 1)"><ChevronRight class="h-4 w-4" /></button>
            </div>
        </div>
    </div>

    <InvoiceDetailModal
        :show="showDetail"
        :invoice="selectedInvoice"
        :can-cancel="canCancelSales && selectedInvoice?.status !== 'cancelled'"
        can-print
        @close="showDetail = false"
        @print="openPrintWindow"
        @cancel="confirmCancel"
    />

    <SaleEditModal v-if="editSale" :sale="editSale" @close="editSale = null" @saved="refreshInvoices" />

    <BaseModal v-if="showCancelModal" title="Xác nhận hủy hóa đơn" size="md" @close="showCancelModal = false">
        <div class="space-y-4 p-1">
            <p class="text-sm text-slate-700">Hủy hóa đơn <strong>#{{ saleToCancel?.code }}</strong> sẽ hoàn số lượng sản phẩm và IMEI liên quan về kho.</p>
            <p v-if="cancelError" class="text-sm text-rose-600">{{ cancelError }}</p>
            <div>
                <label class="mb-1.5 block text-xs font-bold text-slate-700">Lý do hủy</label>
                <textarea v-model="cancelReason" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-rose-400 focus:outline-none" placeholder="Nhập lý do hủy hóa đơn"></textarea>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" @click="showCancelModal = false">Quay lại</button>
                <button type="button" class="rounded-lg bg-rose-600 px-3 py-2 text-sm font-bold text-white hover:bg-rose-700" @click="cancelInvoice">Xác nhận hủy</button>
            </div>
        </div>
    </BaseModal>
    <PrintPreviewModal :show="Boolean(printPreviewUrl)" title="Xem trước hóa đơn" :src="printPreviewUrl" @close="printPreviewUrl = ''" />
</template>
