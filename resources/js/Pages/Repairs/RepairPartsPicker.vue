<script setup>
import { computed, ref, watch, onUnmounted } from 'vue'
import { formatCurrency } from '@/utils/format'
import axios from 'axios'
import debounce from 'lodash/debounce'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import { toast } from 'vue-sonner'

const props = defineProps({ modelValue: { type: Array, default: () => [] } })
const emit = defineEmits(['update:modelValue'])

const keyword = ref('')
const products = ref([])
const loading = ref(false)
const parts = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
})
const money = formatCurrency
let searchSequence = 0

const runSearch = debounce(async (term, sequence) => {
    loading.value = true
    try {
        const { data } = await axios.get('/api/products', { params: { keyword: term } })
        if (sequence === searchSequence) {
            products.value = (Array.isArray(data) ? data : data.data || []).filter((product) => product.product_type !== 'imei' && !product.manage_stock_by_serial)
        }
    } catch {
        if (sequence === searchSequence) toast.error('Không thể tải linh kiện trong kho')
    } finally {
        if (sequence === searchSequence) loading.value = false
    }
}, 250)

watch(keyword, (value) => {
    const term = value.trim()
    const sequence = ++searchSequence
    if (term.length < 2) {
        runSearch.cancel()
        products.value = []
        loading.value = false
        return
    }
    runSearch(term, sequence)
})

onUnmounted(() => {
    searchSequence += 1
    runSearch.cancel()
})

const addPart = (product, variant = null) => {
    const variantId = variant?.id || null
    const stock = Number(variant?.stock ?? product.stock ?? 0)
    const existingIndex = parts.value.findIndex((line) => line.product_id === product.id && line.variant_id === variantId)
    if (existingIndex >= 0) {
        const current = parts.value[existingIndex]
        if (Number(current.quantity) >= stock) {
            closeSuggestions()
            return toast.warning('Số lượng linh kiện đã chọn bằng số tồn kho')
        }
        parts.value = parts.value.map((line, index) => index === existingIndex ? { ...line, quantity: Number(line.quantity || 0) + 1 } : line)
        closeSuggestions()
        return
    }
    if (stock <= 0) {
        closeSuggestions()
        return toast.warning('Linh kiện này đã hết hàng')
    }

    const variantName = variant ? Object.values(variant.attributes || {}).join(' / ') : ''
    const sellPrice = Number(variant?.sell_price ?? product.sell_price ?? product.price ?? 0)
    const costPrice = Number(variant?.cost_price ?? product.cost_price ?? 0)
    parts.value = [...parts.value, {
        product_id: product.id,
        variant_id: variantId,
        product_name: product.name + (variantName ? ` · ${variantName}` : ''),
        sku: variant?.sku || product.sku || '',
        stock,
        quantity: 1,
        unit_price: sellPrice,
        sell_price: sellPrice,
        cost_price: costPrice,
    }]
    closeSuggestions()
}

const closeSuggestions = () => {
    runSearch.cancel()
    keyword.value = ''
    products.value = []
    searchSequence += 1
    loading.value = false
}

const updatePart = (index, key, value) => {
    parts.value = parts.value.map((part, partIndex) => partIndex === index ? { ...part, [key]: value } : part)
}

const removePart = (index) => {
    parts.value = parts.value.filter((_, partIndex) => partIndex !== index)
}
</script>

<template>
    <div class="space-y-2">

        <div class="relative">
            <FloatingInput v-model="keyword" label="Nhập để tìm linh kiện" id="repair_progress_part_search" autocomplete="off" />
            <div v-if="keyword.trim().length >= 2" class="absolute z-30 mt-1 max-h-64 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-xl">
                <p v-if="loading" class="p-3 text-xs text-slate-500">Đang tìm trong kho...</p>
                <p v-else-if="!products.length" class="p-3 text-xs text-slate-500">Không tìm thấy linh kiện phù hợp.</p>
                <div v-for="product in products" :key="product.id" class="border-b border-slate-100 p-2 last:border-0">
                    <button v-if="!product.variants?.length" type="button" class="flex w-full items-center justify-between gap-3 rounded-lg px-2 py-2 text-left transition hover:bg-blue-50 disabled:cursor-not-allowed disabled:opacity-50" :disabled="Number(product.stock || 0) < 1" @click="addPart(product)">
                        <span class="min-w-0"><strong class="block truncate text-sm text-slate-800">{{ product.name }}</strong><small class="block text-slate-500">{{ product.sku || 'Không mã' }} · Tồn {{ product.stock }}</small></span>
                        <span class="shrink-0 text-right"><strong class="block text-xs text-blue-800">Bán {{ money(product.sell_price ?? product.price) }}</strong><small class="block text-[10px] text-slate-400">Nhập {{ money(product.cost_price) }}</small></span>
                    </button>
                    <div v-else>
                        <p class="px-2 pb-1 text-sm font-semibold text-slate-800">{{ product.name }}</p>
                        <div class="space-y-1">
                            <button v-for="variant in product.variants" :key="variant.id" type="button" class="flex w-full items-center justify-between gap-3 rounded-lg px-2 py-1.5 text-left text-xs transition hover:bg-blue-50 disabled:cursor-not-allowed disabled:opacity-50" :disabled="Number(variant.stock || 0) < 1" @click="addPart(product, variant)">
                                <span class="min-w-0"><span class="block truncate font-medium">{{ Object.values(variant.attributes || {}).join(' / ') || variant.sku }}</span><small class="text-slate-500">{{ variant.sku || product.sku || 'Không mã' }} · Tồn {{ variant.stock }}</small></span>
                                <span class="shrink-0 text-right"><strong class="block text-blue-800">Bán {{ money(variant.sell_price) }}</strong><small class="block text-[10px] text-slate-400">Nhập {{ money(variant.cost_price) }}</small></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="parts.length" class="overflow-x-auto rounded-lg border border-blue-100 bg-white">
            <table class="w-full text-xs">
                <thead class="bg-slate-50 text-[10px] uppercase text-slate-500"><tr><th class="p-2 text-left">Linh kiện</th><th class="w-20 p-2">SL</th><th class="p-2 text-right">Giá bán</th><th class="p-2 text-right font-normal text-slate-400">Giá nhập</th><th class="w-8"></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="(part, index) in parts" :key="`${part.product_id}-${part.variant_id}`">
                        <td class="p-2"><strong class="block text-slate-800">{{ part.product_name }}</strong><small class="text-slate-500">{{ part.sku }} · Tồn {{ part.stock }}</small></td>
                        <td class="p-2"><input :value="part.quantity" type="number" min="1" :max="part.stock" class="w-16 rounded-md border-slate-300 px-1.5 py-1 text-center text-xs" @input="updatePart(index, 'quantity', Math.min(Math.max(1, Number($event.target.value || 1)), Number(part.stock)))" /></td>
                        <td class="p-2 text-right font-semibold text-blue-800">{{ money(part.unit_price) }}</td>
                        <td class="p-2 text-right text-slate-400">{{ money(part.cost_price) }}</td>
                        <td class="p-2 text-center"><button type="button" class="text-rose-500 hover:text-rose-700" aria-label="Bỏ linh kiện" @click="removePart(index)">×</button></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p v-else class="rounded-lg border border-dashed border-slate-300 bg-white px-3 py-2 text-center text-xs text-slate-500">Chưa chọn linh kiện thay thế.</p>
    </div>
</template>
