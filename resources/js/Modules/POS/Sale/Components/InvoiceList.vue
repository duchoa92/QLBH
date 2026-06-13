<script setup>

import {
    onMounted,
} from 'vue'

import InvoiceDetailModal from './InvoiceDetailModal.vue'
import { useSaleHistory } from '../Composables/useSaleHistory'

const {
    invoices,
    selectedInvoice,
    showDetail,
    loadInvoices,
    openInvoice,
} = useSaleHistory()

const money = (value) => {

    return Number(value || 0)
        .toLocaleString('vi-VN')

}

onMounted(() => {

    loadInvoices()

})

const formatDate = (date) => {
    return new Date(date).toLocaleString('vi-VN')
}

</script>


<template>

    <div class="p-4">

        <h2 class="font-bold text-lg mb-4">
            Danh sách hóa đơn
        </h2>

        <table class="w-full text-sm border">
            <thead>

                <tr class="bg-gray-100 text-center">
                    <th class="p-2">Mã HĐ</th>
                    <th class="">Khách hàng</th>
                    <th class="">Tổng tiền</th>
                    <th class="">Ngày</th>
                </tr>

            </thead>

            <div v-if="loading" class="p-4">
                Đang tải hóa đơn...
            </div>
            <div v-if="!loading && invoices.length === 0" class="p-4">
                Không có hóa đơn nào
            </div>

            <tbody>

                <tr
                    v-for="invoice in invoices"
                    :key="invoice.id"
                    class="border-b hover:bg-blue-50"
                >
                    <td class="p-2 text-blue-600 font-bold cursor-pointer" @click="openInvoice(invoice.id)">{{ invoice.code }}</td>
                    <td class="text-center">{{ invoice.customer?.full_name ?? 'Khách lẻ' }}</td>
                    <td class="text-right px-3">{{ money(invoice.grand_total) }}</td>
                    <td class="px-3">{{ formatDate(invoice.created_at)}}</td>

                </tr>

                </tbody>
            </table>
    </div>

    <InvoiceDetailModal

        :show="showDetail"
        :invoice="selectedInvoice"
        @close="showDetail=false"

    />

</template>