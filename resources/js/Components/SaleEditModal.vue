<script setup>
import { computed, ref, watch, onMounted } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { Plus, Search, Save, Trash2, MessageSquare, Gift } from 'lucide-vue-next'
import BaseModal from '@/Components/UI/BaseModal.vue'
import { formatMoney, formatDate } from '@/utils/format'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import { findProductByScannedCode } from '@/Modules/POS/Product/Services/productBarcodeLookup'
import BarcodeCameraButton from '@/Components/BarcodeCameraButton.vue'
import CartItem from '@/Modules/POS/Cart/Components/CartItem.vue'
import SelectProductModal from '@/Modules/POS/Product/Components/SelectProductModal.vue'
import CheckoutModal from '@/Modules/POS/Payment/Components/CheckoutModal.vue'

const props = defineProps({ sale: { type: Object, required: true }, blockedReason: { type: String, default: '' } })
const emit = defineEmits(['close', 'saved'])
const attributeLabel = (value) => {
    if (value === null || value === undefined || value === '') return ''
    if (Array.isArray(value)) return value.map(attributeLabel).filter(Boolean).join(' / ')
    if (typeof value === 'object') {
        for (const key of ['value', 'label', 'name', 'title', 'text']) {
            if (value[key] !== undefined && value[key] !== null && value[key] !== '') return attributeLabel(value[key])
        }
        return Object.values(value).map(attributeLabel).filter(Boolean).join(' / ')
    }
    return String(value)
}
const variantName = (variant) => Object.values(variant?.attributes || {}).map(attributeLabel).filter(Boolean).join(' / ') || variant?.name || variant?.sku || ''
const oldInvoiceTotal = Math.max(0, Number(props.sale.grand_total || 0))
const oldChangeRefunded = Math.min(Math.max(0, Number(props.sale.change_amount || 0)), Math.max(0, Number(props.sale.paid_amount || 0)))
const previouslyPaidAmount = Math.min(oldInvoiceTotal, Math.max(0, Number(props.sale.paid_amount || 0) - oldChangeRefunded))
const oldCashAmount = Math.max(0, Number(props.sale.cash_received_amount || 0))
const oldBankAmount = Math.max(0, Number(props.sale.bank_received_amount || 0))
const cashAfterRefund = Math.max(0, oldCashAmount - Math.min(oldCashAmount, oldChangeRefunded))
const bankAfterRefund = Math.max(0, oldBankAmount - Math.max(0, oldChangeRefunded - oldCashAmount))
const previousPaymentTotal = cashAfterRefund + bankAfterRefund
const previousPaymentRatio = previousPaymentTotal > 0 ? previouslyPaidAmount / previousPaymentTotal : 0
const previouslyPaidCash = Math.round(Math.min(previouslyPaidAmount, previousPaymentTotal > 0 ? cashAfterRefund * previousPaymentRatio : (['bank', 'card'].includes(props.sale.payment_method) ? 0 : previouslyPaidAmount)) * 100) / 100
const previouslyPaidBank = previousPaymentTotal > 0
    ? Math.max(0, Math.round((previouslyPaidAmount - previouslyPaidCash) * 100) / 100)
    : (props.sale.payment_method === 'bank' ? previouslyPaidAmount : 0)
const initialPaymentMethod = props.sale.payment_method === 'mixed' && !(previouslyPaidCash > 0 && previouslyPaidBank > 0)
    ? (previouslyPaidBank > 0 ? 'bank' : 'cash')
    : (['cash', 'bank', 'mixed', 'card'].includes(props.sale.payment_method) ? props.sale.payment_method : 'cash')
const form = useForm({
    customer_id: props.sale.customer_id ?? '',
    note: props.sale.note || '',
    paid_amount: previouslyPaidAmount,
    payment_method: initialPaymentMethod,
    bank_account: props.sale.bank_account || '',
    cash_received_amount: previouslyPaidCash,
    bank_received_amount: previouslyPaidBank,
    items: (props.sale.items || []).map(item => ({
        product_id: item.product_id,
        variant_id: item.variant_id ?? '',
        name: item.product?.name || 'Sản phẩm',
        variant_name: variantName(item.variant),
        variant_label: variantName(item.variant),
        unit_name: item.unit_name || item.product?.unit?.short_name || 'Cái',
        image_url: item.product?.image_url || '',
        quantity: Number(item.quantity || 1),
        unit_price: Number(item.unit_price || 0),
        price: Number(item.unit_price || 0),
        discount_type: item.discount_type || 'amount',
        discount_value: item.discount_value ? Number(item.discount_value) : '',
        imei_id: item.product_imei_id ?? '',
        imei: item.product_imei?.imei || '',
        warranty_days: Number(item.warranty_days ?? item.product_imei?.customer_warranty_days ?? item.product?.warranty_days ?? 0),
        warranty_expires_at: item.product_imei?.customer_warranty_expires_at || '',
        warranty_expiry_imei_id: item.product_imei_id ?? '',
        customer_warranty_days: Number(item.warranty_days ?? item.product_imei?.customer_warranty_days ?? item.product?.warranty_days ?? 0),
        warranty_note: item.warranty_note || '',
        customer_warranty_note: item.warranty_note || '',
        note: item.note || '',
        is_imei: Boolean(item.product_imei_id || item.product?.product_type === 'imei' || item.product?.manage_stock_by_serial),
        imei_options: item.product_imei_id ? [{ id: item.product_imei_id, imei: item.product_imei?.imei || 'IMEI đã gán', serial: item.product_imei?.serial }] : [],
        gifts: (item.gifts || []).map(gift => ({ key: `gift-${gift.id}`, id: gift.product_id, product_id: gift.product_id, variant_id: gift.variant_id ?? null, imei_id: gift.product_imei_id ?? null, name: gift.product?.name || 'Quà tặng', sku: gift.product?.sku || '', variant_name: variantName(gift.variant), imei: gift.product_imei?.imei || '', unit_price: Number(gift.product?.sell_price || gift.product?.price || gift.product?.retail_price || 0), is_imei: Boolean(gift.product_imei_id || gift.product?.product_type === 'imei' || gift.product?.manage_stock_by_serial), requires_imei: Boolean(gift.product_imei_id || gift.product?.product_type === 'imei' || gift.product?.manage_stock_by_serial), quantity: Number(gift.quantity || 1), imei_options: gift.product_imei_id ? [{ id: gift.product_imei_id, imei: gift.product_imei?.imei || 'IMEI đã gán', display_code: gift.product_imei?.imei || 'IMEI đã gán' }] : [], variant_options: [] })),
    })),
})
const selectedCustomer = ref(props.sale.customer || null)
const showCheckoutModal = ref(false)
const optionProduct = ref(null)
const optionTargetIndex = ref(null)
const optionPurpose = ref('edit')
const showOptionModal = ref(false)
const openWarrantyNoteIndex = ref(null)
const replacedImeisByItem = ref({})
const customerQuery = ref('')
const customerResults = ref([])
const productQuery = ref('')
const productResults = ref([])
const giftTargetIndex = ref(null)
const activeEditorIndex = ref(null)
const activeEditor = ref(null)
const editorDraft = ref(null)
const choiceKeyword = ref('')
const loadingImeis = ref(false)
const searchingCustomers = ref(false)
const searchingProducts = ref(false)
let customerTimer
let productTimer
const filteredChoiceImeis = computed(() => {
    const keyword = choiceKeyword.value.trim().toLowerCase()
    const imeis = editorDraft.value?.imei_options || []
    if (!keyword) return imeis
    return imeis.filter(imei => [imei.imei, imei.serial, imei.variant_name].some(value => String(value || '').toLowerCase().includes(keyword)))
})
const filteredChoiceVariants = computed(() => (editorDraft.value?.variant_options || []).filter(variant => Number(variant.stock || 0) > 0 || String(variant.id) === String(editorDraft.value?.variant_id)))

onMounted(async () => {
    for (const item of form.items.filter(row => row.is_imei)) item.imei_options = (await loadImeis(item.product_id)).map(imei => ({ ...imei, display_code: imei.imei || imei.serial }))
    for (const item of form.items) {
        for (const gift of item.gifts.filter(row => row.is_imei)) gift.imei_options = (await loadImeis(gift.product_id)).map(imei => ({ ...imei, display_code: imei.imei || imei.serial }))
    }
})

const blockedReason = computed(() => {
    if (props.blockedReason) return props.blockedReason
    if (props.sale.status !== 'completed') return 'Chỉ có thể sửa hóa đơn đang hoàn thành.'
    return ''
})
const subtotal = computed(() => form.items.reduce((sum, row) => sum + Number(row.quantity || 0) * Number(row.unit_price || 0), 0))
const discount = computed(() => form.items.reduce((sum, row) => {
    const amount = Number(row.quantity || 0) * Number(row.unit_price || 0)
    const value = Number(row.discount_value || 0)
    return sum + (row.discount_type === 'percent' ? amount * value / 100 : row.discount_type === 'amount' ? value : 0)
}, 0))
const newGrandTotal = computed(() => Math.max(0, subtotal.value - discount.value + Number(props.sale.tax || 0)))
const previouslyPaid = computed(() => previouslyPaidAmount)
const settlementDifference = computed(() => newGrandTotal.value - previouslyPaid.value)
const remainingBalance = computed(() => Math.max(0, settlementDifference.value))
const changeDue = computed(() => Math.max(0, -settlementDifference.value))

watch(() => form.payment_method, (method) => {
    if (method === 'cash') {
        form.cash_received_amount = Number(form.paid_amount || 0)
        form.bank_received_amount = 0
    } else if (method === 'bank') {
        form.cash_received_amount = 0
        form.bank_received_amount = Number(form.paid_amount || 0)
    } else if (method === 'card') {
        form.cash_received_amount = 0
        form.bank_received_amount = 0
    } else if (Math.abs(Number(form.cash_received_amount || 0) + Number(form.bank_received_amount || 0) - Number(form.paid_amount || 0)) >= 0.01) {
        form.cash_received_amount = Number(form.paid_amount || 0)
        form.bank_received_amount = 0
    }
})
watch(() => form.paid_amount, (amount) => {
    if (form.payment_method === 'cash') form.cash_received_amount = Number(amount || 0)
    if (form.payment_method === 'bank') form.bank_received_amount = Number(amount || 0)
})
watch(() => [form.cash_received_amount, form.bank_received_amount], ([cash, bank]) => {
    if (form.payment_method === 'mixed') form.paid_amount = Number(cash || 0) + Number(bank || 0)
})

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
const loadImeis = async (productId) => {
    loadingImeis.value = true
    try {
        const response = await fetch(route('sales.products.imeis', { product: productId, sale_id: props.sale.id }), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
        return response.ok ? await response.json() : []
    } catch { return [] } finally { loadingImeis.value = false }
}
const addProduct = async (product, variant = null, selectedImei = null) => {
    if (giftTargetIndex.value !== null) {
        const gift = { product_id: product.id, variant_id: variant?.id || selectedImei?.variant_id || '', name: product.name, variant_name: variantName(variant) || variant?.name || selectedImei?.variant_name || '', quantity: 1, is_imei: Boolean(product.is_imei), imei_id: selectedImei?.id || '', imei: selectedImei?.imei || selectedImei?.serial || '', imei_options: [] }
        if (gift.is_imei) gift.imei_options = await loadImeis(product.id)
        if (activeEditor.value === 'gifts' && activeEditorIndex.value === giftTargetIndex.value && editorDraft.value) editorDraft.value.gifts.push(gift)
        else form.items[giftTargetIndex.value]?.gifts.push(gift)
        if (activeEditor.value !== 'gifts') giftTargetIndex.value = null
    } else if (product.is_imei) {
        const imeis = await loadImeis(product.id)
        const selected = selectedImei ? imeis.find(row => String(row.id) === String(selectedImei.id)) || selectedImei : null
        const selectedVariant = selected?.variant || variant
        const price = Number(selected?.effective_sell_price ?? selected?.sell_price ?? selected?.price ?? selectedVariant?.sell_price ?? selectedVariant?.price ?? product.price)
        form.items.push({ product_id: product.id, variant_id: selectedVariant?.id || selected?.variant_id || '', name: product.name, variant_name: selectedVariant ? variantName(selectedVariant) || selectedVariant.name : selected?.variant_name || '', variant_label: selectedVariant ? variantName(selectedVariant) || selectedVariant.name : selected?.variant_name || '', unit_name: 'Cái', image_url: product.image_url || '', quantity: 1, unit_price: price, price, discount_type: 'amount', discount_value: '', is_imei: true, imei_id: selected?.id || '', imei: selected?.imei || selected?.serial || '', warranty_days: Number(product.warranty_days || 0), imei_options: imeis, gifts: [] })
    } else {
        const existing = form.items.find(row => !row.is_imei && Number(row.product_id) === Number(product.id) && Number(row.variant_id || 0) === Number(variant?.id || 0))
        if (existing) existing.quantity++
        else form.items.push({ product_id: product.id, variant_id: variant?.id || '', name: product.name, variant_name: variantName(variant) || variant?.name || '', variant_label: variantName(variant) || variant?.name || '', unit_name: 'Cái', image_url: product.image_url || '', quantity: 1, unit_price: variant?.price ?? product.price, price: variant?.price ?? product.price, discount_type: 'amount', discount_value: '', is_imei: false, imei_id: '', imei: '', warranty_days: Number(product.warranty_days || 0), imei_options: [], gifts: [] })
    }
    productQuery.value = ''
    productResults.value = []
}
const toggleEditor = (index, editor) => {
    if (activeEditorIndex.value === index && activeEditor.value === editor) {
        closeEditor()
        return
    }
    activeEditorIndex.value = index
    activeEditor.value = editor
    editorDraft.value = JSON.parse(JSON.stringify(form.items[index]))
    choiceKeyword.value = ''
    giftTargetIndex.value = editor === 'gifts' ? index : null
    productQuery.value = ''
    productResults.value = []
}
const openImeiSelector = async (index) => {
    const item = form.items[index]
    if (!item?.is_imei) return
    toggleEditor(index, 'imei')
    const available = await loadImeis(item.product_id)
    editorDraft.value.imei_options = available.filter(imei => imei.status === 'in_stock' || String(imei.id) === String(item.imei_id))
}
const openVariantSelector = async (index) => {
    const item = form.items[index]
    if (!item) return
    toggleEditor(index, 'variant')
    try {
        const response = await fetch(route('sales.products.search', { q: item.name }), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
        const products = response.ok ? await response.json() : []
        const variants = products.find(product => Number(product.id) === Number(item.product_id))?.variants || []
        editorDraft.value.variant_options = variants.filter(variant => Number(variant.stock || 0) > 0 || String(variant.id) === String(item.variant_id))
    } catch { editorDraft.value.variant_options = [] }
}
const closeEditor = () => {
    activeEditorIndex.value = null
    activeEditor.value = null
    editorDraft.value = null
    choiceKeyword.value = ''
    giftTargetIndex.value = null
    productQuery.value = ''
    productResults.value = []
}
const saveEditor = () => {
    if (activeEditorIndex.value === null || !editorDraft.value) return
    Object.assign(form.items[activeEditorIndex.value], editorDraft.value)
    closeEditor()
}
const removeItem = (index) => {
    form.items.splice(index, 1)
    if (activeEditorIndex.value === index) {
        closeEditor()
    } else if (activeEditorIndex.value > index) {
        activeEditorIndex.value--
    }
    if (giftTargetIndex.value !== null && giftTargetIndex.value > index) giftTargetIndex.value--
}
const startAddingGift = (index) => {
    if (activeEditorIndex.value !== index || activeEditor.value !== 'gifts') toggleEditor(index, 'gifts')
    giftTargetIndex.value = index
    productQuery.value = ''
    productResults.value = []
}
const setImei = (row, value) => {
    row.imei_id = value
    row.imei = row.imei_options.find(imei => String(imei.id) === String(value))?.imei || ''
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
const warrantyEndDate = (days) => {
    const saleDate = String(props.sale.created_at || '').slice(0, 10)
    if (Number(days || 0) <= 0 || !/^\d{4}-\d{2}-\d{2}$/.test(saleDate)) return ''
    const endDate = new Date(`${saleDate}T00:00:00Z`)
    endDate.setUTCDate(endDate.getUTCDate() + Number(days))
    return formatDate(endDate.toISOString().slice(0, 10))
}
const itemWarrantyEndDate = (item) => {
    if (item.warranty_expiry_imei_id && String(item.warranty_expiry_imei_id) === String(item.imei_id) && item.warranty_expires_at) {
        return formatDate(String(item.warranty_expires_at).slice(0, 10))
    }
    return warrantyEndDate(item.customer_warranty_days ?? item.warranty_days)
}
const persistSaleEdit = () => form.transform(data => ({
    ...data,
    items: data.items.map(item => {
        const { price, customer_warranty_days, customer_warranty_note, ...saleItem } = item
        return {
            ...saleItem,
            unit_price: price ?? item.unit_price,
            warranty_days: customer_warranty_days ?? item.warranty_days,
            warranty_note: customer_warranty_note ?? item.warranty_note,
            gifts: (item.gifts || []).map(gift => ({
                product_id: gift.product_id ?? gift.id,
                variant_id: gift.variant_id || null,
                imei_id: gift.imei_id || null,
                quantity: Number(gift.quantity || 1),
            })),
        }
    }),
})).put(route('sales.update', props.sale.id), {
    preserveScroll: true,
    onSuccess: () => emit('saved'),
})
const submit = () => { showCheckoutModal.value = true }
const confirmCheckoutPayment = (payment) => {
    form.payment_method = payment.payment_method
    form.paid_amount = Number(payment.paid_amount || 0)
    form.cash_received_amount = Number(payment.cash_received_amount || 0)
    form.bank_received_amount = Number(payment.bank_received_amount || 0)
    form.bank_account = payment.bank_account || ''
    persistSaleEdit()
}
const syncSharedCartEditor = ({ item }) => {
    item.unit_price = Number(item.price ?? item.unit_price ?? 0)
    item.warranty_days = item.customer_warranty_days ?? item.warranty_days ?? 0
    item.warranty_note = item.customer_warranty_note || ''
}
const optionCart = computed(() => form.items.filter((_, index) => index !== optionTargetIndex.value))
const addProductCart = computed(() => [
    ...form.items,
    ...form.items.flatMap(item => item.gifts || []),
    ...(editorDraft.value?.gifts || []),
])
const openAddProductOptions = (product) => {
    const allVariants = product.variants || []
    const variants = allVariants.filter(variant => Number(variant.stock || 0) > 0)
    optionProduct.value = { ...product, variants, requires_variant_selection: allVariants.length > 0, product_type: product.is_imei ? 'imei' : 'standard' }
    optionTargetIndex.value = null
    optionPurpose.value = 'add'
    showOptionModal.value = true
}
const openItemOptions = async (index) => {
    const item = form.items[index]
    if (!item) return
    try {
        const response = await fetch(route('sales.products.search', { q: item.name }), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
        const products = response.ok ? await response.json() : []
        const product = products.find(row => Number(row.id) === Number(item.product_id))
        if (!product) return
        optionProduct.value = { ...product, product_type: product.is_imei ? 'imei' : 'standard' }
        optionTargetIndex.value = index
        optionPurpose.value = 'edit'
        showOptionModal.value = true
    } catch { optionProduct.value = null }
}
const currentSelectionImei = computed(() => {
    const item = form.items[optionTargetIndex.value]
    if (!item?.imei_id) return null
    return { id: item.imei_id, imei: item.imei, serial: item.imei_options?.find(row => String(row.id) === String(item.imei_id))?.serial, variant_id: item.variant_id, variant: item.variant_id ? optionProduct.value?.variants?.find(variant => Number(variant.id) === Number(item.variant_id)) : null }
})
const currentSelectionVariant = computed(() => {
    const item = form.items[optionTargetIndex.value]
    return optionProduct.value?.variants?.find(variant => Number(variant.id) === Number(item?.variant_id)) || null
})
const extraSelectionImeis = computed(() => replacedImeisByItem.value[optionTargetIndex.value] || [])
const confirmItemOptions = ({ variant, imei }) => {
    if (optionPurpose.value === 'add') {
        addProduct(optionProduct.value, variant, imei)
        showOptionModal.value = false
        return
    }
    const item = form.items[optionTargetIndex.value]
    if (!item) return
    if (item.is_imei && imei) {
        if (item.imei_id && String(item.imei_id) !== String(imei.id)) {
            const previousImei = item.imei_options?.find(option => String(option.id) === String(item.imei_id))
            if (previousImei) {
                previousImei.variant = optionProduct.value?.variants?.find(row => Number(row.id) === Number(previousImei.variant_id)) || null
                const alternatives = replacedImeisByItem.value[optionTargetIndex.value] || []
                if (!alternatives.some(option => String(option.id) === String(previousImei.id))) {
                    replacedImeisByItem.value[optionTargetIndex.value] = [...alternatives, previousImei]
                }
            }
        }
        item.imei_id = imei.id
        item.imei = imei.imei || imei.serial || ''
        if (variant) {
            item.variant_id = variant.id
            item.variant_name = variantName(variant)
            item.variant_label = item.variant_name
        }
        const selectedPrice = Number(imei.effective_sell_price ?? imei.sell_price ?? imei.price ?? variant?.sell_price ?? variant?.price ?? 0)
        if (selectedPrice > 0) item.unit_price = item.price = selectedPrice
    } else if (variant) {
        item.variant_id = variant.id
        item.variant_name = variantName(variant)
        item.variant_label = item.variant_name
        item.unit_price = item.price = Number(variant.sell_price || variant.price || item.unit_price)
    }
    showOptionModal.value = false
}
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
                    <div class="relative"><FloatingInput v-model="productQuery" :label="giftTargetIndex !== null ? 'Thêm quà tặng' : 'Thêm mặt hàng'" :placeholder-text="giftTargetIndex !== null ? 'Tìm sản phẩm quà tặng theo tên, mã hoặc barcode' : 'Tìm theo tên, mã hoặc barcode'" input-class="!h-10 !rounded-lg !border-slate-300 !py-2 !pr-12 !pl-3 focus:!border-blue-500" @input="searchProducts" @keydown.enter.prevent="scanProduct" /><BarcodeCameraButton class="absolute right-1 top-1/2 -translate-y-1/2 !h-8 !w-8" @scanned="(code) => { productQuery = code; scanProduct() }" /></div>
                    <p v-if="giftTargetIndex !== null" class="mt-1 text-xs text-amber-700">Chọn sản phẩm quà tặng <button type="button" class="underline" @click="giftTargetIndex = null; productQuery = ''; productResults = []">Hủy</button></p>
                    <p v-if="searchingProducts" class="mt-1 text-xs text-slate-400">Đang tìm sản phẩm…</p>
                    <div v-if="productResults.length" class="absolute left-1 right-1 top-[44px] z-20 max-h-56 overflow-y-auto rounded-md border bg-white shadow-lg">
                        <button v-for="product in productResults" :key="product.id" type="button" @click="product.is_imei || product.variants?.length ? openAddProductOptions(product) : addProduct(product)" class="flex w-full items-center justify-between gap-3 border-b px-3 py-2.5 text-left text-sm last:border-b-0 hover:bg-blue-50">
                            <span class="min-w-0"><span class="block truncate font-semibold text-slate-800">{{ product.name }}</span><small class="block truncate text-slate-400">{{ product.sku }} <template v-if="product.is_imei">· Chọn IMEI</template><template v-else-if="product.variants?.length">· Chọn phiên bản</template></small></span>
                            <span class="shrink-0 text-xs font-semibold text-blue-700">{{ product.is_imei || product.variants?.length ? 'Chọn…' : formatMoney(product.price) }}</span>
                        </button>
                        <p v-if="loadingImeis" class="p-3 text-xs text-slate-500">Đang tải IMEI…</p>
                    </div>
                </section>
                </div>

                <section class="overflow-x-auto rounded-lg border border-slate-200">
                    <table class="w-full min-w-[760px] table-fixed text-sm">
                        <colgroup><col/><col class="w-16"/><col class="w-36"/><col class="w-36"/><col class="w-10"/></colgroup>
                        <thead class="bg-slate-50 text-[11px] uppercase text-slate-500"><tr><th class="p-2.5 text-left">Mặt hàng</th><th class="p-2 text-center">SL</th><th class="p-2 text-right">Đơn giá</th><th class="p-2 text-right">Thành tiền</th><th></th></tr></thead>
                        <tbody>
                            <template v-for="(item, index) in form.items" :key="`${item.product_id}-${item.variant_id}-${index}`">
                            <tr class="border-t">
                                <td class="p-2.5">
                                    <CartItem :item="item" editor-only :show-remove="false" :clickable-variant="!item.is_imei && Boolean(item.variant_id || item.variant_name)" :clickable-imei="item.is_imei" @editor-saved="syncSharedCartEditor" @select-variant="openItemOptions(index)" @select-imei="openItemOptions(index)" />
                                    <div v-if="item.customer_warranty_days !== null && item.customer_warranty_days !== undefined || item.warranty_days !== null && item.warranty_days !== undefined || item.customer_warranty_note || item.warranty_note" class="mt-1 flex flex-wrap items-center gap-1.5 pl-[60px]">
                                        <span class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold" :class="Number(item.customer_warranty_days ?? item.warranty_days) > 0 ? 'text-emerald-700' : 'text-slate-500'">{{ Number(item.customer_warranty_days ?? item.warranty_days) > 0 && itemWarrantyEndDate(item) ? `Bảo hành đến ${itemWarrantyEndDate(item)}` : 'Không áp dụng bảo hành' }}</span>
                                        <button v-if="item.customer_warranty_note || item.warranty_note" type="button" class="inline-flex h-6 w-6 items-center justify-center rounded-md text-slate-500 hover:bg-amber-50 hover:text-amber-700" title="Xem ghi chú bảo hành" :aria-expanded="openWarrantyNoteIndex === index" aria-label="Xem ghi chú bảo hành" @click.stop="openWarrantyNoteIndex = openWarrantyNoteIndex === index ? null : index"><MessageSquare :size="14" /></button>
                                    </div>
                                    <div v-if="(item.customer_warranty_note || item.warranty_note) && openWarrantyNoteIndex === index" class="ml-[60px] mt-1 max-w-sm whitespace-pre-line rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-1.5 text-xs font-medium text-amber-900">{{ item.customer_warranty_note || item.warranty_note }}</div>
                                    <div v-if="item.note" class="mt-1 pl-[60px] text-xs italic text-slate-400">Ghi chú: {{ item.note }}</div>
                                </td>
                                <td class="p-1.5"><FloatingInput v-if="!item.is_imei" v-model.number="item.quantity" label="Số lượng" :floating-label="false" type="number" min="1" input-class="!h-9 !rounded-md !px-1 !py-1 !text-center !text-xs" /><span v-else class="block text-center text-xs text-slate-600">1</span></td>
                                <td class="p-1.5"><FloatingInput v-model.number="item.unit_price" label="Đơn giá" :floating-label="false" format-thousands min="0" step="100" input-class="!h-9 !rounded-md !px-2 !py-1 !text-right !text-xs" /></td>
                                <td class="p-2 text-right"><div class="font-bold">{{ formatMoney(lineTotal(item)) }}</div><div v-if="Number(item.discount_value || 0)" class="mt-0.5 text-[10px] font-semibold text-amber-700">Giảm {{ item.discount_type === 'percent' ? `${item.discount_value}%` : formatMoney(item.discount_value) }}</div></td>
                                <td class="p-1"><button type="button" @click="removeItem(index)" class="rounded p-1.5 text-rose-600 hover:bg-rose-50" title="Xóa mặt hàng" aria-label="Xóa mặt hàng"><Trash2 :size="15" /></button></td>
                            </tr>
                            <tr v-for="(gift, giftIndex) in item.gifts || []" :key="`gift-${item.product_id}-${gift.id || giftIndex}`" class="border-t border-emerald-100 bg-emerald-50/50 text-xs">
                                <td class="px-3 py-2 text-emerald-800">
                                    <div class="flex items-start gap-2 pl-2"><Gift :size="14" class="mt-0.5 shrink-0 text-emerald-600"/><div class="min-w-0"><div class="font-bold">{{ gift.name || 'Quà tặng' }}</div><div v-if="gift.variant_name" class="mt-0.5 text-[11px] text-blue-700">Phiên bản: {{ gift.variant_name }}</div><div v-if="gift.imei" class="mt-0.5 font-mono text-[11px] text-blue-700">IMEI: {{ gift.imei }}</div></div><span class="ml-auto shrink-0 rounded-full border border-emerald-200 bg-emerald-100/70 px-2 py-0.5 text-[10px] font-bold text-emerald-700">Quà tặng</span></div>
                                </td>
                                <td class="px-2 py-2 text-center font-semibold text-slate-700">{{ gift.quantity || 1 }}</td>
                                <td class="px-2 py-2 text-right text-slate-400 line-through">{{ formatMoney(gift.unit_price || 0) }}</td>
                                <td class="px-2 py-2 text-right font-bold text-emerald-700">0 đ</td>
                                <td></td>
                            </tr>
                            </template>
                            <tr v-if="!form.items.length"><td colspan="5" class="p-6 text-center text-sm text-slate-500"><Plus class="mx-auto mb-1" :size="18"/>Chưa có mặt hàng trong hóa đơn.</td></tr>
                        </tbody>
                    </table>
                </section>

                <section class="grid gap-2.5 lg:grid-cols-[1fr_280px]">
                    <div class="rounded-lg border border-slate-200 bg-slate-50/50 p-3">
                        <label for="sale-edit-order-note" class="mb-1.5 flex items-center gap-1.5 text-[10px] font-extrabold uppercase tracking-wider text-slate-400"><MessageSquare :size="12" /> Ghi chú đơn hàng</label>
                        <textarea id="sale-edit-order-note" v-model="form.note" rows="3" maxlength="2000" placeholder="Nhập ghi chú cho toàn đơn hàng…" class="w-full resize-y rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-xs text-slate-700 outline-none placeholder:italic placeholder:text-slate-400 focus:border-blue-400 focus:ring-2 focus:ring-blue-100" />
                    </div>
                    <div class="space-y-1.5 rounded-lg border border-slate-200 bg-white p-3 text-xs">
                        <div class="flex justify-between text-slate-500"><span>Tiền hàng</span><b class="text-slate-800">{{ formatMoney(subtotal) }}</b></div>
                        <div class="flex justify-between text-rose-600"><span>Giảm giá</span><b>{{ formatMoney(discount) }}</b></div>
                        <div class="flex justify-between border-t border-dashed border-slate-200 pt-1.5 text-sm font-extrabold text-slate-900"><span>Tổng mới</span><b class="text-blue-700">{{ formatMoney(newGrandTotal) }}</b></div>
                    </div>
                </section>

                <section class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-slate-50/70 p-3">
                    <div class="w-full"><h3 class="text-sm font-bold text-slate-800">Đối soát thanh toán sau khi sửa</h3><p class="mt-0.5 text-xs text-slate-500">Phương thức đã dùng: {{ form.payment_method === 'mixed' ? 'Tiền mặt + chuyển khoản' : form.payment_method === 'bank' ? 'Chuyển khoản' : form.payment_method === 'card' ? 'Thẻ' : 'Tiền mặt' }}</p></div>
                    <div class="grid w-full grid-cols-2 gap-x-4 gap-y-2 text-xs sm:grid-cols-4">
                        <div><p class="text-slate-500">Tổng hóa đơn cũ</p><p class="mt-0.5 font-semibold text-slate-800">{{ formatMoney(sale.grand_total) }}</p></div>
                        <div><p class="text-slate-500">Đã thanh toán trước sửa</p><p class="mt-0.5 font-semibold text-slate-800">{{ formatMoney(previouslyPaid) }}</p></div>
                        <div><p class="text-slate-500">Tổng hóa đơn mới</p><p class="mt-0.5 font-semibold text-blue-700">{{ formatMoney(newGrandTotal) }}</p></div>
                        <div><p class="text-slate-500">{{ remainingBalance > 0 ? 'Cần thu thêm' : changeDue > 0 ? 'Tiền thừa sau sửa' : 'Đối soát' }}</p><p class="mt-0.5 font-bold" :class="remainingBalance > 0 ? 'text-amber-700' : changeDue > 0 ? 'text-emerald-700' : 'text-slate-700'">{{ formatMoney(remainingBalance || changeDue) }}{{ remainingBalance > 0 ? ' cần thu' : changeDue > 0 ? ' dư' : ' Đã đủ' }}</p></div>
                    </div>
                    <p v-if="oldChangeRefunded > 0" class="w-full rounded-md bg-amber-50 px-2.5 py-1.5 text-[11px] font-medium text-amber-800">Đã hoàn {{ formatMoney(oldChangeRefunded) }} tiền thừa của đơn cũ; khoản này không tính vào số đã thanh toán.</p>
                    <p class="w-full text-[11px] text-slate-500">Nhấn “Lưu thay đổi” để mở modal thanh toán POS. Số cần thu hoặc tiền thừa được tính dựa trên khoản đã thanh toán trước khi sửa.</p>
                </section>
                <div v-if="Object.keys(form.errors).length" class="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ form.errors.sale || form.errors.items || Object.values(form.errors)[0] }}</div>
                <div v-if="form.errors.customer_id && !selectedCustomer" class="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ form.errors.customer_id }}</div>
            </div>
        </template>
        <template #footer>
            <div class="flex justify-end gap-2"><button type="button" @click="emit('close')" class="rounded-lg border px-3 py-2 text-sm text-slate-600">Đóng</button><button v-if="!blockedReason" type="button" :disabled="form.processing || !form.items.length" @click="submit" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white disabled:opacity-50"><Save :size="15"/>{{ form.processing ? 'Đang lưu…' : 'Lưu thay đổi' }}</button></div>
        </template>
    </BaseModal>
    <SelectProductModal
        :show="showOptionModal"
        :product="optionProduct"
        :cart="optionPurpose === 'add' ? addProductCart : optionCart"
        :current-imei="currentSelectionImei"
        :current-variant="currentSelectionVariant"
        :extra-imeis="extraSelectionImeis"
        @close="showOptionModal = false"
        @confirm="confirmItemOptions"
    />
    <CheckoutModal
        :show="showCheckoutModal"
        :loading="form.processing"
        :edit-mode="true"
        :invoice-code="String(sale.code || '')"
        :grand-total="newGrandTotal"
        :selected-customer="selectedCustomer"
        :initial-payment="{ payment_method: form.payment_method, bank_account: sale.bank_account, paid_amount: form.paid_amount, cash_received_amount: form.cash_received_amount, bank_received_amount: form.bank_received_amount, refunded_change_amount: oldChangeRefunded }"
        @close="showCheckoutModal = false"
        @confirm="confirmCheckoutPayment"
    />
</template>
