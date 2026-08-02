<script setup>
import { useForm } from '@inertiajs/vue3'

const props = defineProps({
    settings: Object
})

const form = useForm({
    shop_name: props.settings.shop_name || '',
    currency_symbol: props.settings.currency_symbol || '₫',
    currency_format: props.settings.currency_format || 'vi-VN',
    allow_negative_stock: props.settings.allow_negative_stock || false,
})

const save = () => {
    form.post(route('settings.update'))
}
</script>

<template>
<div class="p-4 space-y-4">

    <h2 class="text-xl font-bold">Cài đặt hệ thống</h2>

    <!-- SHOP -->
    <div class="border p-3 rounded">
        <label>Tên cửa hàng *</label>
        <input v-model="form.shop_name" class="border p-2 w-full"/>
    </div>

    <!-- MONEY -->
    <div class="border p-3 rounded space-y-2">
        <label>Ký hiệu tiền</label>
        <input v-model="form.currency_symbol" class="border p-2 w-full"/>

        <label>Định dạng</label>
        <select v-model="form.currency_format" class="border p-2 w-full">
            <option value="vi-VN">Việt Nam</option>
            <option value="en-US">US</option>
        </select>
    </div>

    <!-- PRODUCT -->
    <div class="border p-3 rounded">
        <label>
            <input type="checkbox" v-model="form.allow_negative_stock"/>
            Cho phép tồn kho âm
        </label>
    </div>

    <button @click="save" class="bg-blue-600 text-white px-4 py-2 rounded">
        Lưu cài đặt
    </button>

</div>
</template>