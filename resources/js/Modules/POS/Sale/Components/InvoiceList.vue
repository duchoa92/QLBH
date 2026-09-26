<!-- resources/js/Pages/Pos/InvoiceList.vue -->
<script setup>
import { onMounted } from 'vue'
import InvoiceDetailModal from '@/Components/InvoiceDetailModal.vue'
import { useSaleHistory } from '../Composables/useSaleHistory'

const {
    invoices,
    selectedInvoice,
    showDetail,
    loadInvoices,
    openInvoice,
} = useSaleHistory()

onMounted(() => {
    loadInvoices()
})

const openPrintWindow = (invoice) => {
    if (!invoice?.id) return

    window.open(route('sales.receipt', invoice.id), '_blank', 'width=400,height=650')
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
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="invoice in invoices"
                    :key="invoice.id"
                    class="border-b hover:bg-blue-50"
                    :class="{ 'bg-red-50/40': invoice.status === 'cancelled' }"
                >
                    <td
                        class="p-2 text-blue-600 font-bold cursor-pointer hover:underline"
                        @click="openInvoice(invoice.id)"
                    >
                        {{ invoice.code }}
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
                </tr>
            </tbody>
        </table>
    </div>

    <InvoiceDetailModal
        :show="showDetail"
        :invoice="selectedInvoice"
        can-print
        @close="showDetail = false"
        @print="openPrintWindow"
    />
</template>
