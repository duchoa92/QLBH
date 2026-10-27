<script setup>
import { onMounted, ref } from 'vue'
import BaseModal from '@/Components/UI/BaseModal.vue'
import { formatCurrency } from '@/utils/format'

const props = defineProps({
    productId: { type: [Number, String], required: true },
    productName: { type: String, default: '' },
})

defineEmits(['close'])

const product = ref(null)
const loading = ref(true)
const error = ref('')

const stock = (item) => item?.manage_stock_by_serial
    ? (item.imeis || []).filter(imei => imei.status === 'in_stock').length
    : item?.variants?.length
        ? item.variants.reduce((total, variant) => total + Number(variant.stock || 0), 0)
        : Number(item?.stock || 0)

onMounted(async () => {
    try {
        const { data } = await window.axios.get(route('products.show', props.productId), {
            headers: { Accept: 'application/json' },
        })
        product.value = data.product
    } catch {
        error.value = 'Không tải được thông tin sản phẩm.'
    } finally {
        loading.value = false
    }
})
</script>

<template>
    <BaseModal :title="`Thông tin sản phẩm · ${product?.name || productName}`" size="lg" @close="$emit('close')">
        <div v-if="loading" class="p-8 text-center text-sm text-slate-500">Đang tải thông tin…</div>
        <div v-else-if="error" class="p-6 text-center text-sm text-rose-600">{{ error }}</div>
        <dl v-else-if="product" class="grid gap-3 sm:grid-cols-2">
            <div class="rounded-lg bg-slate-50 p-3"><dt class="text-xs font-semibold text-slate-500">Tên hàng hóa</dt><dd class="mt-1 font-semibold text-slate-900">{{ product.name }}</dd></div>
            <div class="rounded-lg bg-slate-50 p-3"><dt class="text-xs font-semibold text-slate-500">Mã sản phẩm</dt><dd class="mt-1 text-slate-800">{{ product.sku || '—' }}</dd></div>
            <div class="rounded-lg bg-slate-50 p-3"><dt class="text-xs font-semibold text-slate-500">Danh mục</dt><dd class="mt-1 text-slate-800">{{ product.category?.name || '—' }}</dd></div>
            <div class="rounded-lg bg-slate-50 p-3"><dt class="text-xs font-semibold text-slate-500">Thương hiệu</dt><dd class="mt-1 text-slate-800">{{ product.brand?.name || '—' }}</dd></div>
            <div class="rounded-lg bg-slate-50 p-3"><dt class="text-xs font-semibold text-slate-500">Đơn vị</dt><dd class="mt-1 text-slate-800">{{ product.unit?.short_name || product.unit?.name || 'Cái' }}</dd></div>
            <div class="rounded-lg bg-slate-50 p-3"><dt class="text-xs font-semibold text-slate-500">Tồn kho</dt><dd class="mt-1 text-slate-800">{{ stock(product) }} {{ product.unit?.short_name || product.unit?.name || 'Cái' }}</dd></div>
            <div class="rounded-lg bg-slate-50 p-3"><dt class="text-xs font-semibold text-slate-500">Giá nhập</dt><dd class="mt-1 text-slate-800">{{ formatCurrency(product.cost_price) }}</dd></div>
            <div class="rounded-lg bg-slate-50 p-3"><dt class="text-xs font-semibold text-slate-500">Giá bán</dt><dd class="mt-1 font-semibold text-slate-900">{{ formatCurrency(product.sell_price ?? product.price) }}</dd></div>
        </dl>
    </BaseModal>
</template>
