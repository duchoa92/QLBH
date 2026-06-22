<script setup>
import JsBarcode from 'jsbarcode'
import { onMounted } from 'vue'

const props = defineProps({
    products: Array
})

onMounted(() => {
    setTimeout(() => {
        props.products.forEach(p => {
            p.imeis.forEach(i => {
                JsBarcode(`#barcode-${i.id}`, i.imei, {
                    format: "CODE128",
                    width: 1,
                    height: 30,
                    displayValue: false
                })
            })
        })

        window.print()
    }, 300)
})
</script>

<template>
<div class="print-area">

    <div
        v-for="product in products"
        :key="product.id"
    >
        <div
            v-for="imei in product.imeis"
            :key="imei.id"
            class="label"
        >
            <div class="name">{{ product.name }}</div>

            <div class="imei">
                IMEI: {{ imei.imei }}
            </div>

            <div class="price">
                {{ Number(product.sell_price).toLocaleString('vi-VN') }}₫
            </div>

            <!-- Barcode -->
            <svg :id="'barcode-' + imei.id"></svg>
        </div>
    </div>

</div>
</template>

<style>
.print-area {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

/* kích thước tem */
.label {
    width: 180px;
    height: 100px;
    border: 1px dashed #000;
    padding: 6px;
    font-size: 11px;
}

.name {
    font-weight: bold;
    font-size: 12px;
}

.imei {
    margin-top: 2px;
}

.price {
    font-weight: bold;
    margin-top: 2px;
}

@media print {
    body {
        margin: 0;
    }
}
</style>