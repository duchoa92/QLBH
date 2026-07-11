<script setup>
import { onMounted, ref } from 'vue'
import JsBarcode from 'jsbarcode'
import axios from 'axios'

const props = defineProps({
    ids: Array
})

const products = ref([])

onMounted(async () => {
    const res = await axios.post('/products/print-data', {
        ids: props.ids
    })

    products.value = res.data

    setTimeout(() => {
        document.querySelectorAll('.barcode').forEach(el => {
            JsBarcode(el, el.dataset.code, {
                format: 'CODE128',
                width: 2,
                height: 40
            })
        })
    }, 100)
})
</script>

<template>
<div class="p-4 space-y-4">

    <div class="flex justify-end">
        <button onclick="window.print()" class="btn-green">
            In
        </button>
    </div>

    <div v-for="p in products" :key="p.id" class="border p-3">

        <div class="font-bold">{{ p.name }}</div>

        <!-- 👉 nếu có IMEI -->
        <div v-if="p.imeis?.length">
            <div v-for="i in p.imeis" :key="i.id">
                <svg class="barcode" :data-code="i.imei"></svg>
                <div class="text-xs">{{ i.imei }}</div>
            </div>
        </div>

        <!-- 👉 nếu KHÔNG có IMEI -->
        <div v-else>
            <svg class="barcode" :data-code="p.barcode || p.id"></svg>
            <div class="text-xs">{{ p.barcode }}</div>
        </div>

    </div>

</div>
</template>