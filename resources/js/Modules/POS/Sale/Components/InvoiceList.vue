<!-- resources/js/Pages/Pos/InvoiceList.vue -->
<script setup>
import { onMounted, ref, computed } from 'vue'
import axios from 'axios'
import { usePage } from '@inertiajs/vue3'
import { Pencil, Undo2 } from 'lucide-vue-next'
import InvoiceDetailModal from '@/Components/InvoiceDetailModal.vue'
import SaleEditModal from '@/Components/SaleEditModal.vue'
import BaseModal from '@/Components/UI/BaseModal.vue'
import { useSaleHistory } from '../Composables/useSaleHistory'

const {
    invoices,
    selectedInvoice,
    showDetail,
    loadInvoices,
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

onMounted(() => {
    loadInvoices()
})

const openPrintWindow = (invoice) => {
    if (!invoice?.id) return

    window.open(route('sales.receipt', invoice.id), '_blank', 'width=400,height=650')
}

const openEdit = async (invoice) => {
    await openInvoice(invoice.id)
    if (selectedInvoice.value) editSale.value = selectedInvoice.value
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
    <div class="p-4">
        <h2 class="font-bold text-lg mb-4">Danh sách hóa đơn POS</h2>

        <table class="w-full text-sm border">
            <thead>
                <tr class="bg-gray-100 text-center">
                    <th class="p-2">Mã HĐ</th>
                    <th>Khách hàng</th>
                    <th>Trạng thái</th>
                    <th>Tổng tiền</th>
                    <th>Ngày</th>
                    <th class="w-20 text-center">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="invoice in invoices"
                    :key="invoice.id"
                    class="border-b hover:bg-blue-50"
                    :class="{ 'bg-red-50/40': invoice.status === 'cancelled' }"
                    title="Bấm vào hóa đơn để xem chi tiết"
                    @click="openInvoice(invoice.id)"
                >
                    <td class="p-2 text-blue-600 font-bold">
                        #{{ invoice.code }}
                    </td>
                    <td class="text-center">{{ invoice.customer?.full_name ?? 'Khách lẻ' }}</td>
                    <td class="text-center">
                        <span
                            v-if="invoice.status === 'cancelled'"
                            class="px-2 py-0.5 text-xs font-bold text-red-700 bg-red-100 rounded-full"
                        >
                            Đã hủy
                        </span>
                        <span
                            v-else
                            class="px-2 py-0.5 text-xs font-bold text-emerald-700 bg-emerald-100 rounded-full"
                        >
                            Thành công
                        </span>
                    </td>
                    <td class="text-right px-3 font-semibold">{{ $money(invoice.grand_total) }}</td>
                    <td class="px-3 text-center">{{ $dateTime(invoice.created_at) }}</td>
                    <td class="text-center" @click.stop>
                        <div class="inline-flex items-center gap-1">
                            <button v-if="canEditSales" type="button" class="rounded-md p-1.5 text-amber-600 hover:bg-amber-50" title="Sửa hóa đơn" @click="openEdit(invoice)">
                                <Pencil class="h-4 w-4" />
                            </button>
                            <button v-if="canCancelSales && invoice.status !== 'cancelled'" type="button" class="rounded-md p-1.5 text-rose-600 hover:bg-rose-50" title="Hủy hóa đơn và hoàn kho" @click="confirmCancel(invoice)">
                                <Undo2 class="h-4 w-4" />
                            </button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
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
</template>
