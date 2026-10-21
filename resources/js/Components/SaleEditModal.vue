<script setup>
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { Plus, Search, Save, Trash2 } from 'lucide-vue-next'
import BaseModal from '@/Components/UI/BaseModal.vue'
import { formatMoney } from '@/utils/format'
import FormattedNumberInput from '@/Components/UI/FormattedNumberInput.vue'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import { findProductByScannedCode } from '@/Modules/POS/Product/Services/productBarcodeLookup'
import BarcodeCameraButton from '@/Components/BarcodeCameraButton.vue'

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
        discount_type: item.discount_type || 'amount',
        discount_value: item.discount_value ? Number(item.discount_value) : '',
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
    else form.items.push({ product_id: product.id, variant_id: variant?.id || '', name: product.name, variant_name: variant?.name || '', quantity: 1, unit_price: variant?.price ?? product.price, discount_type: 'amount', discount_value: '' })
    productQuery.value = ''
    productResults.value = []
}
const scanProduct = async () => {
    const code = productQuery.value.trim()
    if (!code) return

    clearTimeout(productTimer)
    try {
        const match = await findProductByScannedCode(code)
        if (match) {
            addProduct(match.product, match.variant)
            return
        }
        searchProducts()
    } catch {
        productResults.value = []
    }
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
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <section class="min-w-0 p-1">
                    <div v-if="selectedCustomer" class="flex h-10 w-full items-center justify-between rounded-lg border border-blue-200 bg-blue-50 px-3 text-sm"><span class="min-w-0 truncate font-semibold text-blue-900">{{ selectedCustomer.full_name }} <small class="font-normal text-blue-600">{{ selectedCustomer.phone || '' }}</small></span><button type="button" @click="clearCustomer" class="ml-2 shrink-0 text-xs text-blue-700">Đổi khách</button></div>
                    <div v-else class="relative">
                        <FloatingInput v-model="customerQuery" label="Khách hàng" placeholder-text="Khách lẻ · tìm theo tên hoặc số điện thoại" input-class="!h-10 !rounded-lg !border-slate-300 !py-2 !pl-3 focus:!border-blue-500" @input="searchCustomers" />
                        <div v-if="customerResults.length" class="absolute left-0 right-0 top-full z-20 mt-1 max-h-48 overflow-y-auto rounded-md border bg-white shadow-lg">
                            <button v-for="customer in customerResults" :key="customer.id" type="button" @click="chooseCustomer(customer)" class="block w-full border-b px-3 py-2 text-left text-sm hover:bg-blue-50"><b>{{ customer.full_name }}</b><span class="ml-2 text-xs text-slate-500">{{ customer.phone || customer.code }}</span></button>
                        </div>
                    </div>
                    <p v-if="searchingCustomers" class="mt-1 text-xs text-slate-400">Đang tìm khách hàng…</p>
                    <p v-if="form.errors.customer_id" class="mt-1 text-xs text-rose-600">{{ form.errors.customer_id }}</p>
                </section>

                <section class="relative p-1">
                    <div class="relative"><FloatingInput v-model="productQuery" label="Thêm mặt hàng" placeholder-text="Tìm theo tên, mã hoặc barcode" input-class="!h-10 !rounded-lg !border-slate-300 !py-2 !pr-12 !pl-3 focus:!border-blue-500" @input="searchProducts" @keydown.enter.prevent="scanProduct" /><BarcodeCameraButton class="absolute right-1 top-1/2 -translate-y-1/2 !h-8 !w-8" @scanned="(code) => { productQuery = code; scanProduct() }" /></div>
                    <p v-if="searchingProducts" class="mt-1 text-xs text-slate-400">Đang tìm sản phẩm…</p>
                    <div v-if="productResults.length" class="absolute left-1 right-1 top-[44px] z-20 max-h-56 overflow-y-auto rounded-md border bg-white shadow-lg">
                        <template v-for="product in productResults" :key="product.id">
                            <button v-if="!product.is_imei && !product.variants.length" type="button" @click="addProduct(product)" class="flex w-full items-center justify-between border-b px-3 py-2 text-left text-sm hover:bg-blue-50"><span>{{ product.name }} <small class="text-slate-400">{{ product.sku }}</small></span><span>{{ formatMoney(product.price) }}</span></button>
                            <button v-for="variant in product.variants" :key="variant.id" type="button" @click="addProduct(product, variant)" class="flex w-full items-center justify-between border-b px-3 py-2 text-left text-sm hover:bg-blue-50"><span>{{ variant.name }} <small class="text-slate-400">{{ variant.sku }}</small></span><span>{{ formatMoney(variant.price) }}</span></button>
                        </template>
                        <p v-if="productResults.every(product => product.is_imei)" class="p-3 text-xs text-slate-500">Các sản phẩm quản lý IMEI không thể thêm tại đây.</p>
                    </div>
                </section>
                </div>

                <section class="overflow-x-auto rounded-lg border border-slate-200">
                    <table class="w-full min-w-[720px] text-sm">
                        <thead class="bg-slate-50 text-[11px] uppercase text-slate-500"><tr><th class="p-2.5 text-left">Mặt hàng</th><th class="w-20 p-2">SL</th><th class="w-32 p-2">Đơn giá</th><th class="w-40 p-2">Giảm giá</th><th class="w-32 p-2 text-right">Thành tiền</th><th class="w-9"></th></tr></thead>
                        <tbody>
                            <tr v-for="(item, index) in form.items" :key="`${item.product_id}-${item.variant_id}`" class="border-t">
                                <td class="p-2.5 font-semibold">{{ item.name }}<small v-if="item.variant_name" class="block text-slate-500">{{ item.variant_name }}</small></td>
                                <td class="p-1.5"><input v-model.number="item.quantity" type="number" min="1" class="w-full rounded-md border border-slate-300 bg-white px-2 py-2 text-center text-sm shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100" /></td>
                                <td class="p-1.5"><FormattedNumberInput v-model.number="item.unit_price" min="0" step="100" class="w-full rounded-md border border-slate-300 bg-white px-2 py-2 text-right text-sm shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100" /></td>
                                <td class="p-1.5"><div class="flex min-w-0 items-stretch"><FormattedNumberInput v-if="item.discount_type !== 'percent'" v-model.number="item.discount_value" min="0" placeholder="0" class="min-w-0 flex-1 rounded-l-md border border-slate-300 bg-white px-2 py-2 text-right text-sm shadow-sm outline-none transition focus:z-10 focus:border-blue-500 focus:ring-2 focus:ring-blue-100" /><input v-else v-model.number="item.discount_value" type="number" min="0" max="100" placeholder="0" class="min-w-0 flex-1 rounded-l-md border border-slate-300 bg-white px-2 py-2 text-right text-sm shadow-sm outline-none transition focus:z-10 focus:border-blue-500 focus:ring-2 focus:ring-blue-100" /><select v-model="item.discount_type" class="w-[4.25rem] shrink-0 rounded-r-md border border-l-0 border-slate-300 bg-slate-50 px-1.5 py-2 text-sm font-medium outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"><option value="percent">%</option><option value="amount">₫</option></select></div></td>
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
