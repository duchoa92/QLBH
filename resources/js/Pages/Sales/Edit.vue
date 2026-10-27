<script setup>
import { computed, ref } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import { ArrowLeft, Plus, Search, Trash2, Save } from 'lucide-vue-next'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { formatMoney } from '@/utils/format'
import FormattedNumberInput from '@/Components/UI/FormattedNumberInput.vue'
import { findProductByScannedCode } from '@/Modules/POS/Product/Services/productBarcodeLookup'
import BarcodeCameraButton from '@/Components/BarcodeCameraButton.vue'
import { toast } from 'vue-sonner'

defineOptions({ layout: AdminLayout })
const props = defineProps({ sale: Object, customers: Array, blockedReason: String })
const form = useForm({
    customer_id: props.sale.customer_id ?? '',
    items: (props.sale.items || []).map(item => ({
        product_id: item.product_id,
        variant_id: item.variant_id ?? '',
        name: item.product?.name || 'Sản phẩm',
        variant_name: item.variant?.attributes ? Object.values(item.variant.attributes).filter(Boolean).join(' / ') : '',
        quantity: Number(item.quantity || 1),
        unit_price: Number(item.unit_price || 0),
        discount_type: item.discount_type || '',
        discount_value: Number(item.discount_value || 0),
    })),
})
const search = ref('')
const results = ref([])
let searchTimer
const searchProducts = () => {
    clearTimeout(searchTimer)
    if (search.value.trim().length < 2) { results.value = []; return }
    searchTimer = setTimeout(async () => {
        try {
            const response = await fetch(route('sales.products.search', { q: search.value }), { headers: { Accept: 'application/json' } })
            results.value = await response.json()
        } catch { results.value = [] }
    }, 250)
}
const addProduct = (product, variant = null) => {
    if (product.is_imei) return
    const existing = form.items.find(row => Number(row.product_id) === Number(product.id) && Number(row.variant_id || 0) === Number(variant?.id || 0))
    if (existing) existing.quantity++
    else form.items.push({ product_id: product.id, variant_id: variant?.id || '', name: product.name, variant_name: variant?.name || '', quantity: 1, unit_price: variant?.price ?? product.price, discount_type: '', discount_value: 0 })
    search.value = ''
    results.value = []
}
const scanProduct = async () => {
    const code = search.value.trim()
    if (!code) return

    clearTimeout(searchTimer)
    try {
        const match = await findProductByScannedCode(code)
        if (match) {
            addProduct(match.product, match.variant)
            return
        }
        searchProducts()
    } catch {
        toast.error('Không tìm thấy sản phẩm theo mã vừa quét')
    }
}
const subtotal = computed(() => form.items.reduce((sum, row) => sum + Number(row.quantity || 0) * Number(row.unit_price || 0), 0))
const discount = computed(() => form.items.reduce((sum, row) => {
    const amount = Number(row.quantity || 0) * Number(row.unit_price || 0)
    const value = Number(row.discount_value || 0)
    return sum + (row.discount_type === 'percent' ? amount * value / 100 : row.discount_type === 'amount' ? value : 0)
}, 0))
const submit = () => form.put(route('sales.update', props.sale.id))
</script>

<template>
    <div class="mx-auto max-w-5xl space-y-4 p-3 text-slate-800">
        <div class="flex items-center justify-between rounded-xl border bg-white p-4">
            <div><Link :href="route('sales.show', sale.id)" class="inline-flex items-center gap-1 text-xs text-slate-500"><ArrowLeft :size="14" />Quay lại hóa đơn</Link></div>
            <button v-if="!blockedReason" :disabled="form.processing" @click="submit" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white disabled:opacity-50"><Save :size="16" />Lưu thay đổi</button>
        </div>

        <div v-if="blockedReason" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">{{ blockedReason }}</div>
        <template v-else>
            <div class="rounded-xl border bg-white p-4">
                <label class="mb-1 block text-xs font-bold text-slate-600">Khách hàng</label>
                <select v-model="form.customer_id" class="w-full rounded-lg border border-slate-300 p-2.5 text-sm"><option value="">Khách lẻ</option><option v-for="customer in customers" :key="customer.id" :value="customer.id">{{ customer.full_name }} · {{ customer.phone || 'Không có SĐT' }}</option></select>
                <p v-if="form.errors.customer_id" class="mt-1 text-xs text-red-600">{{ form.errors.customer_id }}</p>
            </div>

            <div class="relative rounded-xl border bg-white p-4">
                <label class="mb-1 block text-xs font-bold text-slate-600">Thêm sản phẩm</label>
                <div class="relative flex items-center rounded-lg border px-3"><Search :size="16" class="shrink-0 text-slate-400"/><input v-model="search" @input="searchProducts" @keydown.enter.prevent="scanProduct" placeholder="Tìm theo tên, mã hoặc barcode" class="w-full border-0 py-2.5 pr-10 text-sm outline-none focus:ring-0" /><BarcodeCameraButton class="absolute right-1 top-1/2 -translate-y-1/2 !h-8 !w-8" @scanned="(code) => { search = code; scanProduct() }" /></div>
                <div v-if="results.length" class="absolute left-4 right-4 top-[78px] z-10 max-h-64 overflow-y-auto rounded-lg border bg-white shadow-lg">
                    <template v-for="product in results" :key="product.id">
                        <button v-if="!product.is_imei && !product.variants.length" type="button" @click="addProduct(product)" class="flex w-full items-center justify-between border-b px-3 py-2 text-left text-sm hover:bg-blue-50"><span>{{ product.name }} <small class="text-slate-400">{{ product.sku }}</small></span><span>{{ formatMoney(product.price) }}</span></button>
                        <div v-for="variant in product.variants" :key="variant.id" class="border-b">
                            <button type="button" @click="addProduct(product, variant)" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-blue-50"><span>{{ variant.name }} <small class="text-slate-400">{{ variant.sku }}</small></span><span>{{ formatMoney(variant.price) }}</span></button>
                        </div>
                    </template>
                </div>
            </div>

            <div class="overflow-x-auto rounded-xl border bg-white">
                <table class="app-data-table w-full min-w-[800px] text-sm">
                    <thead class="bg-slate-50 text-xs text-slate-500"><tr><th class="p-3 text-left">Mặt hàng</th><th class="p-3 w-24">Số lượng</th><th class="p-3 w-36">Đơn giá</th><th class="p-3 w-40">Giảm giá</th><th class="p-3 w-36 text-right">Thành tiền</th><th class="p-3 w-12"></th></tr></thead>
                    <tbody>
                        <tr v-for="(item, index) in form.items" :key="`${item.product_id}-${item.variant_id}`" class="border-t">
                            <td class="p-3 font-semibold">{{ item.name }} <small v-if="item.variant_name" class="block text-slate-500">{{ item.variant_name }}</small></td>
                            <td class="p-2"><input v-model.number="item.quantity" type="number" min="1" class="w-full rounded border p-2 text-center" /></td>
                            <td class="p-2"><FormattedNumberInput v-model.number="item.unit_price" min="0" step="100" class="w-full rounded border p-2 text-right" /></td>
                            <td class="p-2"><div class="flex gap-1"><select v-model="item.discount_type" class="w-20 rounded border p-2"><option value="">Không</option><option value="percent">%</option><option value="amount">₫</option></select><FormattedNumberInput v-if="item.discount_type === 'amount'" v-model.number="item.discount_value" min="0" class="min-w-0 flex-1 rounded border p-2" /><input v-else-if="item.discount_type === 'percent'" v-model.number="item.discount_value" type="number" min="0" max="100" class="min-w-0 flex-1 rounded border p-2" /></div></td>
                            <td class="p-3 text-right font-bold">{{ formatMoney(Math.max(0, item.quantity * item.unit_price - (item.discount_type === 'percent' ? item.quantity * item.unit_price * item.discount_value / 100 : item.discount_type === 'amount' ? item.discount_value : 0))) }}</td>
                            <td class="p-2"><button type="button" @click="form.items.splice(index, 1)" class="rounded p-2 text-rose-600 hover:bg-rose-50"><Trash2 :size="16" /></button></td>
                        </tr>
                        <tr v-if="!form.items.length"><td colspan="6" class="p-8 text-center text-slate-500">Chưa có mặt hàng trong hóa đơn.</td></tr>
                    </tbody>
                </table>
                <div class="space-y-1 border-t bg-slate-50 p-4 text-right text-sm"><p>Tạm tính: <b>{{ formatMoney(subtotal) }}</b></p><p>Giảm giá: <b>{{ formatMoney(discount) }}</b></p><p class="text-base">Tổng hóa đơn mới: <b class="text-blue-700">{{ formatMoney(Math.max(0, subtotal - discount + Number(sale.tax || 0))) }}</b></p><p class="text-xs text-slate-500">Đã thanh toán: {{ formatMoney(sale.paid_amount) }} · Tiền thu không thay đổi khi sửa.</p></div>
            </div>
            <p v-if="form.errors.items || form.errors.sale || form.errors.customer_id" class="rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ form.errors.items || form.errors.sale || form.errors.customer_id }}</p>
            <p v-if="form.errors.product_id" class="rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ form.errors.product_id }}</p>
        </template>
    </div>
</template>
