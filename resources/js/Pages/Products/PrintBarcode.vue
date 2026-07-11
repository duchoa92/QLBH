<script setup>
import { onMounted } from 'vue'
import JsBarcode from 'jsbarcode'

const props = defineProps({
    products: Array
})

onMounted(() => {
    props.products.forEach((p, i) => {
        const code = p.barcode || p.sku || p.id

        JsBarcode(`#barcode-${i}`, code, {
            format: "CODE128",
            width: 2,
            height: 40,
            displayValue: false
        })
    })

    // auto print
    setTimeout(() => window.print(), 500)
})
</script>

<template>
<div class="print-area">

    <div
        v-for="(p, i) in products"
        :key="i"
        class="label"
    >
        <div class="name">{{ p.name }}</div>

        <svg :id="'barcode-' + i"></svg>

        <div class="price">
            {{ Number(p.sell_price).toLocaleString('vi-VN') }} đ
        </div>
    </div>

</div>
</template>

<style scoped>
/* ===== KÍCH THƯỚC TEM ===== */
@page {
    size: 50mm 30mm;
    margin: 0;
}

.print-area {
    display: flex;
    flex-wrap: wrap;
}

/* ===== 1 TEM ===== */
.label {
    width: 50mm;
    height: 30mm;
    padding: 2mm;
    box-sizing: border-box;

    display: flex;
    flex-direction: column;
    justify-content: space-between;

    border: 1px dashed #ddd; /* debug, xóa khi in */
}

/* ===== TEXT ===== */
.name {
    font-size: 10px;
    font-weight: 600;
    text-align: center;
    line-height: 1.2;
    height: 20px;
    overflow: hidden;
}

.price {
    font-size: 11px;
    font-weight: bold;
    text-align: center;
}

/* ===== ẨN UI KHI IN ===== */
@media print {
    body {
        margin: 0;
    }

    .label {
        border: none;
    }
}
</style>