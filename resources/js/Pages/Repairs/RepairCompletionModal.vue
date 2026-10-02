<script setup>
import { computed, ref, watch } from 'vue'
import axios from 'axios'
import BaseModal from '@/Components/UI/BaseModal.vue'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import ActionButton from '@/Components/UI/ActionButton.vue'
import { toast } from 'vue-sonner'

const props = defineProps({ repair: { type: Object, required: true }, inline: Boolean })
const emit = defineEmits(['close', 'updated'])

const keyword = ref('')
const products = ref([])
const parts = ref([])
const laborCost = ref('')
const surcharge = ref('')
const loadingProducts = ref(false)
const saving = ref(false)
const errors = ref({})
let searchTimer
let searchSequence = 0

const totalParts = computed(() => parts.value.reduce((sum, item) => sum + Number(item.quantity || 0) * Number(item.unit_price || 0), 0))
const total = computed(() => totalParts.value + Number(laborCost.value || 0) + Number(surcharge.value || 0))
const money = (value) => `${Number(value || 0).toLocaleString('vi-VN')} đ`

watch(keyword, (value) => {
    clearTimeout(searchTimer)
    const term = value.trim()
    if (term.length < 2) {
        products.value = []
        return
    }
    searchTimer = setTimeout(async () => {
        const sequence = ++searchSequence
        loadingProducts.value = true
        try {
            const { data } = await axios.get('/api/products', { params: { keyword: term } })
            if (sequence === searchSequence) {
                products.value = (Array.isArray(data) ? data : data.data || []).filter((product) => product.product_type !== 'imei' && !product.manage_stock_by_serial)
            }
        } catch {
            toast.error('Không thể tải danh sách linh kiện')
        } finally {
            if (sequence === searchSequence) loadingProducts.value = false
        }
    }, 250)
})

const addPart = (product, variant = null) => {
    const existing = parts.value.find((line) => line.product_id === product.id && line.variant_id === (variant?.id || null))
    if (existing) {
        existing.quantity = Math.min(Number(existing.quantity) + 1, Number(variant?.stock ?? product.stock ?? 0))
        return
    }
    const stock = Number(variant?.stock ?? product.stock ?? 0)
    if (stock <= 0) return toast.warning('Linh kiện này đã hết hàng')
    const variantName = variant ? Object.values(variant.attributes || {}).join(' / ') : ''
    parts.value.push({
        product_id: product.id,
        variant_id: variant?.id || null,
        product_name: product.name + (variantName ? ` · ${variantName}` : ''),
        sku: variant?.sku || product.sku || '',
        stock,
        quantity: 1,
        unit_price: Number(variant?.sell_price ?? product.sell_price ?? product.price ?? 0),
    })
}

const submit = async () => {
    saving.value = true
    errors.value = {}
    try {
        await axios.post(route('repairs.complete', props.repair.id), {
            parts: parts.value.map(({ product_id, variant_id, quantity, unit_price }) => ({ product_id, variant_id, quantity: Number(quantity), unit_price: Number(unit_price) })),
            labor_cost: Number(laborCost.value || 0),
            surcharge: Number(surcharge.value || 0),
        })
        toast.success('Đã hoàn tất sửa chữa')
        emit('updated', { ...props.repair, final_cost: total.value, parts_total: totalParts.value, labor_cost: Number(laborCost.value || 0), surcharge: Number(surcharge.value || 0), status: 'done' })
        emit('close')
    } catch (error) {
        errors.value = error.response?.data?.errors || {}
        toast.error(Object.values(errors.value).flat()[0] || 'Không thể hoàn tất sửa chữa')
    } finally {
        saving.value = false
    }
}
</script>

<template>
    <component :is="inline ? 'section' : BaseModal" :title="inline ? undefined : `Hoàn tất sửa · ${repair.code}`" :size="inline ? undefined : 'lg'" :class="inline ? 'rounded-xl border border-emerald-200 bg-white p-3' : ''" @close="emit('close')">
        <h3 v-if="inline" class="mb-3 text-sm font-bold text-emerald-800">Hoàn tất sửa · tính chi phí</h3>
        <div class="space-y-4">
            <div class="rounded-xl bg-slate-50 p-3 text-sm text-slate-600">{{ repair.device_name }} <span v-if="repair.customer?.name">· {{ repair.customer.name }}</span></div>
            <div class="relative">
                <FloatingInput v-model="keyword" label="Tìm linh kiện thay thế theo tên hoặc mã" id="repair_part_search" />
                <div v-if="keyword.trim().length >= 2" class="absolute z-20 mt-1 max-h-64 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-lg">
                    <p v-if="loadingProducts" class="p-3 text-sm text-slate-500">Đang tìm linh kiện...</p>
                    <p v-else-if="!products.length" class="p-3 text-sm text-slate-500">Không tìm thấy linh kiện phù hợp</p>
                    <div v-for="product in products" :key="product.id" class="border-b border-slate-100 p-2 last:border-0">
                        <button v-if="!product.variants?.length" type="button" class="flex w-full items-center justify-between rounded-lg px-2 py-1.5 text-left hover:bg-blue-50" @click="addPart(product)">
                            <span><strong class="block text-sm">{{ product.name }}</strong><small class="text-slate-500">{{ product.sku || 'Không mã' }} · Tồn {{ product.stock }}</small></span>
                            <span class="text-sm font-semibold">{{ money(product.sell_price ?? product.price) }}</span>
                        </button>
                        <div v-else>
                            <p class="px-2 pb-1 text-sm font-semibold">{{ product.name }}</p>
                            <div class="flex flex-wrap gap-1">
                                <button v-for="variant in product.variants" :key="variant.id" type="button" class="rounded-lg border border-slate-200 px-2 py-1 text-xs hover:border-blue-400 hover:bg-blue-50 disabled:opacity-40" :disabled="Number(variant.stock) < 1" @click="addPart(product, variant)">
                                    {{ Object.values(variant.attributes || {}).join(' / ') || variant.sku }} · {{ money(variant.sell_price) }} · Tồn {{ variant.stock }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <p v-if="errors.parts" class="text-sm text-rose-600">{{ errors.parts[0] }}</p>
            <div v-if="parts.length" class="overflow-x-auto rounded-xl border border-slate-200">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="p-2 text-left">Linh kiện</th><th class="p-2 w-24">SL</th><th class="p-2 w-36">Đơn giá</th><th class="p-2 w-32 text-right">Thành tiền</th><th class="w-10"></th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="(part, index) in parts" :key="`${part.product_id}-${part.variant_id}`">
                            <td class="p-2"><div class="font-medium">{{ part.product_name }}</div><small class="text-slate-500">{{ part.sku }} · Tồn {{ part.stock }}</small></td>
                            <td class="p-2"><input v-model.number="part.quantity" type="number" min="1" :max="part.stock" class="w-20 rounded-lg border-slate-300 text-sm" /></td>
                            <td class="p-2"><input v-model.number="part.unit_price" type="number" min="0" class="w-32 rounded-lg border-slate-300 text-sm" /></td>
                            <td class="p-2 text-right font-semibold">{{ money(part.quantity * part.unit_price) }}</td>
                            <td class="p-2"><button type="button" class="text-rose-600 hover:text-rose-800" aria-label="Xóa linh kiện" @click="parts.splice(index, 1)">×</button></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <FloatingInput v-model="laborCost" type="number" min="0" step="1000" label="Công sửa (VNĐ)" id="repair_labor_cost" :error="errors.labor_cost?.[0]" />
                <FloatingInput v-model="surcharge" type="number" min="0" step="1000" label="Phụ phí (VNĐ)" id="repair_surcharge" :error="errors.surcharge?.[0]" />
            </div>
            <div class="space-y-1 rounded-xl border border-emerald-100 bg-emerald-50 p-3 text-sm">
                <div class="flex justify-between"><span>Tiền linh kiện</span><strong>{{ money(totalParts) }}</strong></div>
                <div class="flex justify-between"><span>Công sửa + phụ phí</span><strong>{{ money(Number(laborCost || 0) + Number(surcharge || 0)) }}</strong></div>
                <div class="flex justify-between border-t border-emerald-200 pt-2 text-base font-bold text-emerald-800"><span>Thành tiền</span><span>{{ money(total) }}</span></div>
            </div>
        </div>
        <div v-if="inline" class="mt-4 flex justify-end">
            <ActionButton :disabled="saving" @click="submit">{{ saving ? 'Đang lưu...' : 'Hoàn tất sửa' }}</ActionButton>
        </div>
        <template v-if="!inline" #footer>
            <div class="flex justify-end gap-2"><ActionButton variant="secondary" @click="emit('close')">Hủy</ActionButton><ActionButton :disabled="saving" @click="submit">{{ saving ? 'Đang lưu...' : 'Hoàn tất sửa' }}</ActionButton></div>
        </template>
    </component>
</template>
