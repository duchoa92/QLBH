<script setup>
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { Plus, Search, Save, Trash2 } from 'lucide-vue-next'
import BaseModal from '@/Components/UI/BaseModal.vue'
import { formatMoney } from '@/utils/format'

const props = defineProps({ sale: { type: Object, required: true } })
const emit = defineEmits(['close', 'saved'])
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
const selectedCustomer = ref(props.sale.customer || null)
const customerQuery = ref('')
const customerResults = ref([])
const productQuery = ref('')
const productResults = ref([])
const searchingCustomers = ref(false)
const searchingProducts = ref(false)
let customerTimer
let productTimer

const blockedReason = computed(() => {
    if (props.sale.status !== 'completed') return 'Chỉ có thể sửa hóa đơn đang hoàn thành.'
    if ((props.sale.items || []).some(item => item.product_imei_id || (item.gifts || []).length)) return 'Hóa đơn có IMEI hoặc quà tặng; hiện chưa hỗ trợ sửa loại hóa đơn này.'
    return ''
})
const subtotal = computed(() => form.items.reduce((sum, row) => sum + Number(row.quantity || 0) * Number(row.unit_price || 0), 0))
const discount = computed(() => form.items.reduce((sum, row) => {
    const amount = Number(row.quantity || 0) * Number(row.unit_price || 0)
    const value = Number(row.discount_value || 0)
    return sum + (row.discount_type === 'percent' ? amount * value / 100 : row.discount_type === 'amount' ? value : 0)
}, 0))

const searchCustomers = () => {
    clearTimeout(customerTimer)
    if (customerQuery.value.trim().length < 2) { customerResults.value = []; return }
    customerTimer = setTimeout(async () => {
        searchingCustomers.value = true
        try {
            const response = await fetch(route('sales.customers.search', { q: customerQuery.value }), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            customerResults.value = response.ok ? await response.json() : []
        } catch { customerResults.value = [] } finally { searchingCustomers.value = false }
    }, 250)
}
const chooseCustomer = (customer) => {
    selectedCustomer.value = customer
    form.customer_id = customer.id
    customerQuery.value = ''
    customerResults.value = []
}
const clearCustomer = () => {
    selectedCustomer.value = null
    form.customer_id = ''
    customerQuery.value = ''
    customerResults.value = []
}
const searchProducts = () => {
    clearTimeout(productTimer)
    if (productQuery.value.trim().length < 2) { productResults.value = []; return }
    productTimer = setTimeout(async () => {
        searchingProducts.value = true
        try {
            const response = await fetch(route('sales.products.search', { q: productQuery.value }), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            productResults.value = response.ok ? await response.json() : []
        } catch { productResults.value = [] } finally { searchingProducts.value = false }
    }, 250)
}
const addProduct = (product, variant = null) => {
    if (product.is_imei) return
    const existing = form.items.find(row => Number(row.product_id) === Number(product.id) && Number(row.variant_id || 0) === Number(variant?.id || 0))
    if (existing) existing.quantity++
    else form.items.push({ product_id: product.id, variant_id: variant?.id || '', name: product.name, variant_name: variant?.name || '', quantity: 1, unit_price: variant?.price ?? product.price, discount_type: '', discount_value: 0 })
    productQuery.value = ''
    productResults.value = []
}
const lineTotal = (item) => {
    const amount = Number(item.quantity || 0) * Number(item.unit_price || 0)
    const value = Number(item.discount_value || 0)
    return Math.max(0, amount - (item.discount_type === 'percent' ? amount * value / 100 : item.discount_type === 'amount' ? value : 0))
}
const submit = () => form.put(route('sales.update', props.sale.id), {
    preserveScroll: true,
    onSuccess: () => emit('saved'),
})
</script>

<template>
    <BaseModal :title="`Sửa hóa đơn #${sale.code}`" size="xl" body-class="p-0" @close="emit('close')">
        <template v-if="blockedReason">
            <div class="m-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">{{ blockedReason }}</div>
        </template>
        <template v-else>
            <div class="space-y-3 p-3 sm:p-4">
                <section class="rounded-lg border border-slate-200 p-3">
                    <div class="mb-1 flex items-center justify-between"><label class="text-xs font-bold text-slate-600">Khách hàng</label><button v-if="selectedCustomer" type="button" @click="clearCustomer" class="text-xs text-slate-500 hover:text-rose-600">Bỏ chọn · Khách lẻ</button></div>
                    <div v-if="selectedCustomer" class="mb-2 flex items-center justify-between rounded-md bg-blue-50 px-3 py-2 text-sm"><span class="font-semibold">{{ selectedCustomer.full_name }} <small class="text-slate-500">{{ selectedCustomer.phone || '' }}</small></span><button type="button" @click="clearCustomer" class="text-xs text-blue-700">Đổi khách</button></div>
                    <div class="relative">
                        <div class="flex items-center gap-2 rounded-md border px-3"><Search :size="15" class="text-slate-400"/><input v-model="customerQuery" @input="searchCustomers" placeholder="Tìm khách theo tên hoặc số điện thoại" class="w-full border-0 py-2 text-sm outline-none focus:ring-0" /></div>
                        <div v-if="customerResults.length" class="absolute left-0 right-0 top-full z-20 mt-1 max-h-48 overflow-y-auto rounded-md border bg-white shadow-lg">
                            <button v-for="customer in customerResults" :key="customer.id" type="button" @click="chooseCustomer(customer)" class="block w-full border-b px-3 py-2 text-left text-sm hover:bg-blue-50"><b>{{ customer.full_name }}</b><span class="ml-2 text-xs text-slate-500">{{ customer.phone || customer.code }}</span></button>
                        </div>
                    </div>
                    <p v-if="searchingCustomers" class="mt-1 text-xs text-slate-400">Đang tìm khách hàng…</p>
                    <p v-if="form.errors.customer_id" class="mt-1 text-xs text-rose-600">{{ form.errors.customer_id }}</p>
                </section>

                <section class="relative rounded-lg border border-slate-200 p-3">
                    <label class="mb-1 block text-xs font-bold text-slate-600">Thêm mặt hàng</label>
                    <div class="flex items-center gap-2 rounded-md border px-3"><Search :size="15" class="text-slate-400"/><input v-model="productQuery" @input="searchProducts" placeholder="Tìm theo tên, mã hoặc barcode" class="w-full border-0 py-2 text-sm outline-none focus:ring-0" /></div>
                    <p v-if="searchingProducts" class="mt-1 text-xs text-slate-400">Đang tìm sản phẩm…</p>
                    <div v-if="productResults.length" class="absolute left-3 right-3 top-[70px] z-20 max-h-56 overflow-y-auto rounded-md border bg-white shadow-lg">
                        <template v-for="product in productResults" :key="product.id">
                            <button v-if="!product.is_imei && !product.variants.length" type="button" @click="addProduct(product)" class="flex w-full items-center justify-between border-b px-3 py-2 text-left text-sm hover:bg-blue-50"><span>{{ product.name }} <small class="text-slate-400">{{ product.sku }}</small></span><span>{{ formatMoney(product.price) }}</span></button>
                            <button v-for="variant in product.variants" :key="variant.id" type="button" @click="addProduct(product, variant)" class="flex w-full items-center justify-between border-b px-3 py-2 text-left text-sm hover:bg-blue-50"><span>{{ variant.name }} <small class="text-slate-400">{{ variant.sku }}</small></span><span>{{ formatMoney(variant.price) }}</span></button>
                        </template>
                        <p v-if="productResults.every(product => product.is_imei)" class="p-3 text-xs text-slate-500">Các sản phẩm quản lý IMEI không thể thêm tại đây.</p>
                    </div>
                </section>

                <section class="overflow-x-auto rounded-lg border border-slate-200">
                    <table class="w-full min-w-[720px] text-sm">
                        <thead class="bg-slate-50 text-[11px] uppercase text-slate-500"><tr><th class="p-2.5 text-left">Mặt hàng</th><th class="w-20 p-2">SL</th><th class="w-32 p-2">Đơn giá</th><th class="w-36 p-2">Giảm giá</th><th class="w-32 p-2 text-right">Thành tiền</th><th class="w-9"></th></tr></thead>
                        <tbody>
                            <tr v-for="(item, index) in form.items" :key="`${item.product_id}-${item.variant_id}`" class="border-t">
                                <td class="p-2.5 font-semibold">{{ item.name }}<small v-if="item.variant_name" class="block text-slate-500">{{ item.variant_name }}</small></td>
                                <td class="p-1.5"><input v-model.number="item.quantity" type="number" min="1" class="w-full rounded border p-1.5 text-center" /></td>
                                <td class="p-1.5"><input v-model.number="item.unit_price" type="number" min="0" step="100" class="w-full rounded border p-1.5 text-right" /></td>
                                <td class="p-1.5"><div class="flex gap-1"><select v-model="item.discount_type" class="w-16 rounded border p-1.5"><option value="">Không</option><option value="percent">%</option><option value="amount">₫</option></select><input v-if="item.discount_type" v-model.number="item.discount_value" type="number" min="0" :max="item.discount_type === 'percent' ? 100 : undefined" class="min-w-0 flex-1 rounded border p-1.5" /></div></td>
                                <td class="p-2 text-right font-bold">{{ formatMoney(lineTotal(item)) }}</td>
                                <td class="p-1"><button type="button" @click="form.items.splice(index, 1)" class="rounded p-1.5 text-rose-600 hover:bg-rose-50" title="Xóa mặt hàng"><Trash2 :size="15" /></button></td>
                            </tr>
                            <tr v-if="!form.items.length"><td colspan="6" class="p-6 text-center text-sm text-slate-500"><Plus class="mx-auto mb-1" :size="18"/>Chưa có mặt hàng trong hóa đơn.</td></tr>
                        </tbody>
                    </table>
                    <div class="space-y-1 border-t bg-slate-50 px-3 py-2 text-right text-xs"><p>Tạm tính: <b>{{ formatMoney(subtotal) }}</b></p><p>Giảm giá: <b>{{ formatMoney(discount) }}</b></p><p class="text-sm">Tổng mới: <b class="text-blue-700">{{ formatMoney(Math.max(0, subtotal - discount + Number(sale.tax || 0))) }}</b></p><p class="text-[11px] text-slate-500">Đã thu {{ formatMoney(sale.paid_amount) }} · Số tiền đã thu được giữ nguyên.</p></div>
                </section>
                <div v-if="Object.keys(form.errors).length" class="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ form.errors.sale || form.errors.items || Object.values(form.errors)[0] }}</div>
                <div v-if="form.errors.customer_id && !selectedCustomer" class="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ form.errors.customer_id }}</div>
            </div>
        </template>
        <template #footer>
            <div class="flex justify-end gap-2"><button type="button" @click="emit('close')" class="rounded-lg border px-3 py-2 text-sm text-slate-600">Đóng</button><button v-if="!blockedReason" type="button" :disabled="form.processing || !form.items.length" @click="submit" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white disabled:opacity-50"><Save :size="15"/>{{ form.processing ? 'Đang lưu…' : 'Lưu thay đổi' }}</button></div>
        </template>
    </BaseModal>
</template>
