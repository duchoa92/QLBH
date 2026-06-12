<script setup>

import {
    ref,
    onMounted,
} from 'vue'


import InvoiceList from '../Components/InvoiceList.vue'

import {
    saleService
} from '../Services/saleService'


const invoices = ref([])


const loading = ref(false)



const loadSales = async () => {

    try {

        loading.value = true


        const response =
            await saleService.getSales()


        invoices.value =
            response.data
// debug
console.log(
    'INVOICES',
    invoices.value
)

    }
    catch(error){

        console.error(error)

    }
    finally {

        loading.value = false

    }

}



onMounted(()=>{

    loadSales()

})


</script>



<template>


<div class="p-4">


<h1 class="text-xl font-bold mb-4">

Lịch sử bán hàng

</h1>


<div
v-if="loading"
>
Đang tải...
</div>


<InvoiceList

v-else

:invoices="invoices"

/>


</div>


</template>