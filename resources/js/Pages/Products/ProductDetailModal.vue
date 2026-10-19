<script setup>
import { computed } from 'vue'
import BaseModal from '@/Components/UI/BaseModal.vue'
import { closeModal } from '@/Stores/modal'
import { formatCurrency } from '@/utils/format'

const props = defineProps({ product: { type: Object, required: true } })

const money = value => formatCurrency(Number(value || 0))
const unitName = unit => unit?.short_name || unit?.name || ''
const variantName = variant => {
    const rawAttributes = variant?.attributes
    const attributes = Array.isArray(rawAttributes)
        ? rawAttributes
        : Object.entries(rawAttributes || {}).map(([name, value]) => ({ name, value }))
    const values = attributes.map(attribute => {
        const value = Array.isArray(attribute.value) ? attribute.value.join(', ') : attribute.value
        return value ? `${attribute.name}: ${value}` : attribute.name
    }).filter(Boolean)
    return values.join(' · ') || variant?.sku || `Biến thể #${variant?.id}`
}

const variantById = computed(() => new Map((props.product.variants || []).map(variant => [Number(variant.id), variant])))
const imeiVariantName = imei => variantName(variantById.value.get(Number(imei.variant_id)))
const imeiRows = computed(() => [...(props.product.imeis || [])].sort((a, b) => {
    const dateA = new Date(a.imported_at || a.created_at || 0).getTime()
    const dateB = new Date(b.imported_at || b.created_at || 0).getTime()
    return dateB - dateA || Number(b.id) - Number(a.id)
}))
const hasImeiTracking = computed(() => Boolean(props.product.manage_stock_by_serial) || imeiRows.value.length > 0)
const latestImeiSellPrice = computed(() => {
    const latestImei = imeiRows.value[0]
    if (!latestImei) return props.product.sell_price
    return latestImei.sell_price
        ?? variantById.value.get(Number(latestImei.variant_id))?.sell_price
        ?? props.product.sell_price
})
const imeiInStockCount = computed(() => imeiRows.value.filter(imei => imei.status === 'in_stock').length)
const productStockCount = computed(() => {
    if (hasImeiTracking.value) return imeiInStockCount.value
    if (props.product.variants?.length) {
        return props.product.variants.reduce((total, variant) => total + Number(variant.stock || 0), 0)
    }
    return Number(props.product.stock || 0)
})
const statusLabel = status => status === 'in_stock' ? 'Trong kho' : status === 'sold' ? 'Đã bán' : (status || 'Khác')
const statusClass = status => status === 'in_stock' ? 'bg-emerald-100 text-emerald-700' : status === 'sold' ? 'bg-slate-100 text-slate-600' : 'bg-amber-100 text-amber-700'
</script>

<template>
    <BaseModal :title="`Chi tiết sản phẩm · ${product.name}`" size="2xl" body-class="space-y-4 p-4 sm:p-5" @close="closeModal()">
        <section class="grid gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3 sm:grid-cols-2 lg:grid-cols-4">
            <div><div class="text-xs text-slate-500">Mã sản phẩm</div><div class="font-semibold">{{ product.sku || '—' }}</div></div>
            <div><div class="text-xs text-slate-500">Danh mục / thương hiệu</div><div class="font-semibold">{{ product.category?.name || '—' }} / {{ product.brand?.name || '—' }}</div></div>
            <div><div class="text-xs text-slate-500">Tồn tổng</div><div class="font-semibold">{{ productStockCount }} {{ unitName(product.unit) }}</div><div v-if="hasImeiTracking" class="text-[11px] text-slate-500">Tính theo IMEI còn trong kho</div><div v-else-if="product.variants?.length" class="text-[11px] text-slate-500">Tổng tồn các biến thể</div></div>
            <div><div class="text-xs text-slate-500">{{ hasImeiTracking ? 'Giá bán mới nhất' : 'Giá bán chung' }}</div><div class="font-semibold">{{ money(hasImeiTracking ? latestImeiSellPrice : product.sell_price) }}</div></div>
        </section>

        <section v-if="!hasImeiTracking && product.variants?.length" class="overflow-hidden rounded-xl border border-slate-200">
            <h3 class="border-b bg-slate-50 px-3 py-2 font-semibold text-slate-800">Biến thể · giá và tồn từng loại</h3>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[620px] text-sm">
                    <thead class="text-left text-xs uppercase text-slate-500"><tr><th class="px-3 py-2">Biến thể</th><th class="px-3 py-2">SKU / barcode</th><th class="px-3 py-2 text-right">Giá vốn</th><th class="px-3 py-2 text-right">Giá bán</th><th class="px-3 py-2 text-right">Tồn</th></tr></thead>
                    <tbody>
                        <tr v-for="variant in product.variants" :key="variant.id" class="border-t border-slate-100">
                            <td class="px-3 py-2 font-medium">{{ variantName(variant) }}</td>
                            <td class="px-3 py-2">{{ variant.sku || '—' }}<span v-if="variant.barcode" class="block text-xs text-slate-500">{{ variant.barcode }}</span></td>
                            <td class="px-3 py-2 text-right">{{ money(variant.cost_price) }}</td>
                            <td class="px-3 py-2 text-right font-semibold">{{ money(variant.sell_price) }}</td>
                            <td class="px-3 py-2 text-right">{{ variant.stock || 0 }} {{ unitName(product.unit) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section v-if="hasImeiTracking" class="overflow-hidden rounded-xl border border-slate-200">
            <h3 class="border-b bg-slate-50 px-3 py-2 font-semibold text-slate-800">IMEI · giá và trạng thái từng máy ({{ imeiRows.length }} mã, {{ imeiInStockCount }} còn trong kho)</h3>
            <div class="overflow-x-auto">
                <table v-if="imeiRows.length" class="w-full min-w-[760px] text-sm">
                    <thead class="text-left text-xs uppercase text-slate-500"><tr><th class="px-3 py-2">IMEI / sê-ri</th><th class="px-3 py-2">Biến thể</th><th class="px-3 py-2">Trạng thái</th><th class="px-3 py-2 text-right">Giá vốn</th><th class="px-3 py-2 text-right">Giá bán</th><th class="px-3 py-2">BH NCC đến</th></tr></thead>
                    <tbody>
                        <tr v-for="imei in imeiRows" :key="imei.id" class="border-t border-slate-100">
                            <td class="px-3 py-2 font-mono">{{ imei.imei || '—' }}<span v-if="imei.serial" class="block text-xs text-slate-500">{{ imei.serial }}</span></td>
                            <td class="px-3 py-2">{{ imei.variant_id ? imeiVariantName(imei) : '—' }}</td>
                            <td class="px-3 py-2"><span class="rounded-full px-2 py-1 text-xs font-semibold" :class="statusClass(imei.status)">{{ statusLabel(imei.status) }}</span></td>
                            <td class="px-3 py-2 text-right">{{ money(imei.cost_price ?? variantById.get(Number(imei.variant_id))?.cost_price ?? product.cost_price) }}</td>
                            <td class="px-3 py-2 text-right font-semibold">{{ money(imei.sell_price ?? variantById.get(Number(imei.variant_id))?.sell_price ?? product.sell_price) }}</td>
                            <td class="px-3 py-2">{{ imei.warranty_expired_at ? String(imei.warranty_expired_at).slice(0, 10) : '—' }}</td>
                        </tr>
                    </tbody>
                </table>
                <p v-else class="px-3 py-4 text-sm text-slate-500">Sản phẩm quản lý theo IMEI nhưng chưa có mã IMEI trong kho.</p>
            </div>
        </section>

        <div v-if="!hasImeiTracking && !product.variants?.length" class="rounded-lg border border-dashed border-slate-300 p-4 text-center text-sm text-slate-500">
            Tồn hiện tại: {{ productStockCount }} {{ unitName(product.unit) }} · Giá bán: {{ money(product.sell_price) }}.
        </div>

        <template #footer>
            <div class="flex justify-end"><button type="button" class="rounded-lg bg-slate-200 px-4 py-2 text-sm font-semibold text-slate-700" @click="closeModal()">Đóng</button></div>
        </template>
    </BaseModal>
</template>
