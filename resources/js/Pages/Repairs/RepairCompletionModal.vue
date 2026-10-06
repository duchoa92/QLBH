<script setup>
import { computed, ref, watch } from 'vue'
import axios from 'axios'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import ActionButton from '@/Components/UI/ActionButton.vue'
import { toast } from 'vue-sonner'

const props = defineProps({ repair: { type: Object, required: true } })
const emit = defineEmits(['updated'])

const keyword = ref('')
const products = ref([])
const parts = ref([])
const laborCost = ref('')
const surcharge = ref('')
const warrantyCoveredAmount = ref(Number(props.repair.warranty_covered_amount || 0))
const repairWarrantyDays = ref(30)
const coverageTouched = ref(false)
const billingType = ref('service')
const declineWarranty = ref(false)
const declineReason = ref('')
const loadingProducts = ref(false)
const saving = ref(false)
const errors = ref({})
let searchTimer
let searchSequence = 0

const totalParts = computed(() => parts.value.reduce((sum, item) => sum + Number(item.quantity || 0) * Number(item.unit_price || 0), 0))
const total = computed(() => totalParts.value + Number(laborCost.value || 0) + Number(surcharge.value || 0))
const hasWarrantyCandidate = computed(() => {
    if (!props.repair.warranty_source_type || !props.repair.warranty_source_id || !props.repair.warranty_expires_at || props.repair.warranty_status === 'declined') return false
    const expiresAt = new Date(props.repair.warranty_expires_at)
    expiresAt.setHours(23, 59, 59, 999)
    return expiresAt.getTime() >= Date.now()
})
const isWarrantyBilling = computed(() => billingType.value === 'warranty')
const coveredAmount = computed(() => isWarrantyBilling.value ? Math.min(total.value, Number(warrantyCoveredAmount.value || 0)) : 0)
const payableTotal = computed(() => Math.max(0, total.value - coveredAmount.value))
const money = (value) => `${Number(value || 0).toLocaleString('vi-VN')} đ`

watch([total, billingType], ([value, type]) => {
    if (type === 'warranty' && !coverageTouched.value) warrantyCoveredAmount.value = value
});

watch(billingType, (value) => {
    declineWarranty.value = false
    if (value === 'warranty') {
        coverageTouched.value = false
        warrantyCoveredAmount.value = total.value
    }
});

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
            billing_type: billingType.value,
            decline_warranty: declineWarranty.value,
            decline_reason: declineWarranty.value ? declineReason.value.trim() : null,
            parts: parts.value.map(({ product_id, variant_id, quantity, unit_price }) => ({ product_id, variant_id, quantity: Number(quantity), unit_price: Number(unit_price) })),
            labor_cost: Number(laborCost.value || 0),
            surcharge: Number(surcharge.value || 0),
            warranty_covered_amount: coveredAmount.value,
            repair_warranty_days: Number(repairWarrantyDays.value || 0),
        })
        toast.success('Đã hoàn tất sửa chữa')
        emit('updated', { ...props.repair, intake_type: isWarrantyBilling.value ? 'warranty' : 'repair', warranty_status: declineWarranty.value ? 'declined' : (isWarrantyBilling.value ? 'accepted' : (hasWarrantyCandidate.value ? 'service' : null)), warranty_covered_amount: coveredAmount.value, final_cost: payableTotal.value, parts_total: totalParts.value, labor_cost: Number(laborCost.value || 0), surcharge: Number(surcharge.value || 0), status: 'done' })
    } catch (error) {
        errors.value = error.response?.data?.errors || {}
        toast.error(Object.values(errors.value).flat()[0] || 'Không thể hoàn tất sửa chữa')
    } finally {
        saving.value = false
    }
}
</script>

<template>
    <section class="rounded-xl border border-emerald-200 bg-white p-3">
        <h3 class="mb-3 text-sm font-bold text-emerald-800">Hoàn tất sửa · chọn loại xử lý và tính chi phí</h3>
        <div class="space-y-4">
            <div class="rounded-xl bg-slate-50 p-3 text-sm text-slate-600">{{ repair.device_name }} <span v-if="repair.customer?.name">· {{ repair.customer.name }}</span></div>
            <div class="space-y-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
                <div class="text-xs font-bold uppercase tracking-wide text-slate-600">Chốt loại sửa sau kiểm tra</div>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <button type="button" class="rounded-xl border px-3 py-2 text-left text-sm font-semibold transition" :class="billingType === 'service' ? 'border-blue-400 bg-blue-50 text-blue-800 ring-1 ring-blue-200' : 'border-slate-200 bg-white text-slate-600'" @click="billingType = 'service'">
                        Sửa dịch vụ
                        <span class="mt-0.5 block text-[11px] font-normal">Khách thanh toán theo chi phí thực tế</span>
                    </button>
                    <button v-if="hasWarrantyCandidate" type="button" class="rounded-xl border px-3 py-2 text-left text-sm font-semibold transition" :class="billingType === 'warranty' ? 'border-emerald-400 bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200' : 'border-slate-200 bg-white text-slate-600'" @click="billingType = 'warranty'">
                        Bảo hành
                        <span class="mt-0.5 block text-[11px] font-normal">Chọn phần chi phí được bảo hành</span>
                    </button>
                    <div v-if="!hasWarrantyCandidate && repair.warranty_source_type" class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 sm:col-span-2">
                        Căn cứ bảo hành đã hết hạn hoặc không còn hiệu lực; phiếu sẽ tính theo sửa dịch vụ.
                    </div>
                </div>
            </div>
            <div v-if="isWarrantyBilling" class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-xs text-emerald-900">
                <strong>Phiếu xử lý bảo hành</strong>
                <span v-if="repair.warranty_expires_at"> · Căn cứ còn hạn đến {{ new Date(repair.warranty_expires_at).toLocaleDateString('vi-VN') }}</span>
                <p class="mt-1">Phần chi phí được duyệt bảo hành sẽ trừ khỏi số tiền khách cần thanh toán; phần ngoài phạm vi vẫn tính phí.</p>
            </div>
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
            <div v-if="isWarrantyBilling" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <FloatingInput v-model.number="warrantyCoveredAmount" type="number" min="0" :max="total" step="1000" label="Chi phí được bảo hành (VNĐ)" id="repair_warranty_covered" @input="coverageTouched = true" />
                <FloatingInput v-model.number="repairWarrantyDays" type="number" min="0" max="3650" step="1" label="BH cho lần sửa này (ngày, 0 = không BH)" id="repair_warranty_days" />
            </div>
            <div v-else class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <FloatingInput v-model.number="repairWarrantyDays" type="number" min="0" max="3650" step="1" label="BH cho lần sửa này (ngày, 0 = không BH)" id="repair_warranty_days" />
            </div>
            <div v-if="hasWarrantyCandidate && billingType === 'service'" class="space-y-2 rounded-xl border border-amber-200 bg-amber-50 p-3">
                <label class="flex cursor-pointer items-start gap-2 text-xs text-amber-900">
                    <input v-model="declineWarranty" type="checkbox" class="mt-0.5 rounded border-amber-400 text-amber-600 focus:ring-amber-500" />
                    <span><strong>Từ chối yêu cầu bảo hành này.</strong> Hạn bảo hành gốc sẽ bị vô hiệu cho các lần tiếp nhận sau.</span>
                </label>
                <FloatingInput v-if="declineWarranty" v-model="declineReason" label="Lý do từ chối bảo hành *" id="repair_warranty_decline_reason" :error="errors.decline_reason?.[0]" />
            </div>
            <div class="space-y-1 rounded-xl border border-emerald-100 bg-emerald-50 p-3 text-sm">
                <div class="flex justify-between"><span>Tiền linh kiện</span><strong>{{ money(totalParts) }}</strong></div>
                <div class="flex justify-between"><span>Công sửa + phụ phí</span><strong>{{ money(Number(laborCost || 0) + Number(surcharge || 0)) }}</strong></div>
                <div v-if="isWarrantyBilling" class="flex justify-between"><span>Tổng chi phí sửa</span><strong>{{ money(total) }}</strong></div>
                <div v-if="isWarrantyBilling" class="flex justify-between text-emerald-700"><span>Được bảo hành</span><strong>− {{ money(coveredAmount) }}</strong></div>
                <div class="flex justify-between border-t border-emerald-200 pt-2 text-base font-bold text-emerald-800"><span>{{ isWarrantyBilling ? 'Khách cần thanh toán' : 'Thành tiền' }}</span><span>{{ money(payableTotal) }}</span></div>
            </div>
        </div>
        <div class="mt-4 flex justify-end">
            <ActionButton :disabled="saving || (declineWarranty && declineReason.trim().length < 5)" @click="submit">{{ saving ? 'Đang lưu...' : 'Hoàn tất sửa' }}</ActionButton>
        </div>
    </section>
</template>
