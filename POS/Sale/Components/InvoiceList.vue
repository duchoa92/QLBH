<script setup>

import {
    ref,
    onMounted,
} from 'vue'

import { saleService } from '../Services/saleService'

import InvoiceDetailModal from './InvoiceDetailModal.vue'


const invoices = ref([])

const selectedInvoice = ref(null)

const showDetail = ref(false)


const money = (value) => {

    return Number(value || 0)
        .toLocaleString('vi-VN')

}


// Load danh sách

const loadInvoices = async () => {

    try {

        invoices.value =
            await saleService.getAll()

    } catch(error) {

        console.error(error)

    }

}


// Click hóa đơn

const openInvoice = async (id) => {


    try {

        selectedInvoice.value =
            await saleService.show(id)


        showDetail.value = true


    } catch(error) {

        console.error(error)

    }

}


onMounted(() => {

    loadInvoices()

})


</script>


<template>


<div class="p-4">


<h2 class="font-bold text-lg mb-4">

Danh sách hóa đơn

</h2>



<table
class="w-full text-sm border"
>


<thead>

<tr class="bg-gray-100">

<th class="p-2 text-left">
Mã HĐ
</th>


<th>
Khách hàng
</th>


<th>
Tổng tiền
</th>


<th>
Ngày
</th>


</tr>

</thead>



<tbody>


<tr
v-for="invoice in invoices"
:key="invoice.id"
class="border-b hover:bg-blue-50"
>


<td
class="p-2 text-blue-600 font-bold cursor-pointer"
@click="openInvoice(invoice.id)"
>

{{ invoice.code }}

</td>



<td>

{{ invoice.customer?.full_name ?? 'Khách lẻ' }}

</td>



<td class="text-right">

{{ money(invoice.grand_total) }}

</td>



<td>

{{ invoice.created_at }}

</td>


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