<script setup>
import { computed, ref, watch } from 'vue'
import axios from 'axios'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import { ShieldCheck, Wrench } from 'lucide-vue-next'
import ActionButton from '@/Components/UI/ActionButton.vue'
import { toast } from 'vue-sonner'
import { formatCurrency, formatDate } from '@/utils/format'

const props = defineProps({ repair: { type: Object, required: true }, showSubmit: { type: Boolean, default: true }, modelValue: { type: Array, default: () => [] }, declineWarranty: { type: Boolean, default: false }, declineReason: { type: String, default: '' } })
const emit = defineEmits(['updated', 'update:modelValue'])

const parts = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
})
const laborCost = ref('')
const surcharge = ref('')
const warrantyCoveredAmount = ref(Number(props.repair.warranty_covered_amount || 0))
const repairWarrantyDays = ref(30)
const coverageTouched = ref(false)
const billingType = ref('service')
const saving = ref(false)
const errors = ref({})

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
const money = formatCurrency

watch([total, billingType], ([value, type]) => {
    if (type === 'warranty' && !coverageTouched.value) warrantyCoveredAmount.value = value
});

watch(() => props.declineWarranty, (declined) => {
    if (declined) billingType.value = 'service'
}, { immediate: true })

const selectBillingType = (value) => {
    billingType.value = value
    if (value === 'warranty') {
        coverageTouched.value = false
        warrantyCoveredAmount.value = total.value
    }
}

const submit = async () => {
    saving.value = true
    errors.value = {}
    try {
        await axios.post(route('repairs.complete', props.repair.id), {
            billing_type: billingType.value,
            decline_warranty: props.declineWarranty,
            decline_reason: props.declineWarranty ? props.declineReason.trim() : null,
            parts: parts.value.map(({ product_id, variant_id, quantity, unit_price }) => ({ product_id, variant_id, quantity: Number(quantity), unit_price: Number(unit_price) })),
            labor_cost: Number(laborCost.value || 0),
            surcharge: Number(surcharge.value || 0),
            warranty_covered_amount: coveredAmount.value,
            repair_warranty_days: Number(repairWarrantyDays.value || 0),
        })
        toast.success('Đã hoàn tất sửa chữa')
        emit('updated', { ...props.repair, intake_type: isWarrantyBilling.value ? 'warranty' : 'repair', warranty_status: props.declineWarranty ? 'declined' : (isWarrantyBilling.value ? 'accepted' : (hasWarrantyCandidate.value ? 'service' : null)), warranty_covered_amount: coveredAmount.value, final_cost: payableTotal.value, parts_total: totalParts.value, labor_cost: Number(laborCost.value || 0), surcharge: Number(surcharge.value || 0), status: 'done' })
    } catch (error) {
        errors.value = error.response?.data?.errors || {}
        toast.error(Object.values(errors.value).flat()[0] || 'Không thể hoàn tất sửa chữa')
    } finally {
        saving.value = false
    }
}

defineExpose({ submit, saving: computed(() => saving.value) })
</script>

<template>
    <section class="rounded-xl border border-emerald-200 bg-white p-3">
        <h3 class="mb-3 text-sm font-bold text-emerald-800">Hoàn tất sửa · chọn loại xử lý và tính chi phí</h3>
        <div class="space-y-4"><div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                <div v-if="props.declineWarranty" class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2.5 text-sm font-semibold text-rose-800 sm:col-span-2">Đã chọn từ chối bảo hành · phiếu sẽ tính theo sửa dịch vụ.</div>
                <button v-else type="button" class="flex items-center gap-2 rounded-xl border px-3 py-2.5 text-left text-sm font-semibold transition" :class="billingType === 'service' ? 'border-blue-400 bg-blue-50 text-blue-800 ring-1 ring-blue-200' : 'border-slate-200 bg-white text-slate-600'" @click="selectBillingType('service')"><Wrench class="h-4 w-4" />Sửa dịch vụ</button>
                <button v-if="!props.declineWarranty" type="button" class="flex items-center gap-2 rounded-xl border px-3 py-2.5 text-left text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-50" :class="billingType === 'warranty' ? 'border-emerald-400 bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200' : 'border-slate-200 bg-white text-slate-600'" :disabled="!hasWarrantyCandidate" :title="!hasWarrantyCandidate ? 'Phiếu không có căn cứ bảo hành còn hiệu lực' : ''" @click="selectBillingType('warranty')"><ShieldCheck class="h-4 w-4" />Bảo hành</button>
                <div v-if="!hasWarrantyCandidate && repair.warranty_source_type" class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800 sm:col-span-2">
                    Căn cứ bảo hành đã hết hạn hoặc không còn hiệu lực; phiếu sẽ tính theo sửa dịch vụ.
                </div>
            </div>
            <div v-if="isWarrantyBilling" class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-xs text-emerald-900">
                <strong>Phiếu xử lý bảo hành</strong>
                <span v-if="repair.warranty_expires_at"> · Căn cứ còn hạn đến {{ formatDate(repair.warranty_expires_at) }}</span>
                <p class="mt-1">Phần chi phí được duyệt bảo hành sẽ trừ khỏi số tiền khách cần thanh toán; phần ngoài phạm vi vẫn tính phí.</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-3">
                <div class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-600">Linh kiện đã chọn ở bước sửa ngay</div>
                <div v-if="parts.length" class="overflow-x-auto rounded-lg border border-slate-100">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-[10px] uppercase text-slate-500"><tr><th class="p-2 text-left">Linh kiện</th><th class="p-2 text-center">SL</th><th class="p-2 text-right">Giá bán</th><th class="p-2 text-right font-normal text-slate-400">Giá nhập</th><th class="p-2 text-right">Thành tiền</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="part in parts" :key="`${part.product_id}-${part.variant_id}`">
                                <td class="p-2"><span class="font-medium text-slate-800">{{ part.product_name }}</span><small class="ml-1 text-slate-400">{{ part.sku }}</small></td>
                                <td class="p-2 text-center">{{ part.quantity }}</td>
                                <td class="p-2 text-right font-semibold">{{ money(part.unit_price) }}</td>
                                <td class="p-2 text-right text-slate-400">{{ money(part.cost_price) }}</td>
                                <td class="p-2 text-right font-semibold">{{ money(part.quantity * part.unit_price) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="text-xs text-slate-500">Không sử dụng linh kiện thay thế.</p>
            </div>
            <p v-if="errors.parts" class="text-sm text-rose-600">{{ errors.parts[0] }}</p>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <FloatingInput v-model="laborCost" type="number" min="0" step="1000" label="Công sửa (VNĐ)" id="repair_labor_cost" :error="errors.labor_cost?.[0]" />
                <FloatingInput v-model="surcharge" type="number" min="0" step="1000" label="Phụ phí (VNĐ)" id="repair_surcharge" :error="errors.surcharge?.[0]" />
                <FloatingInput v-model.number="repairWarrantyDays" type="number" min="0" max="3650" step="1" label="BH cho lần sửa này (ngày, 0 = không BH)" id="repair_warranty_days" />
            </div>
            <div v-if="isWarrantyBilling" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <FloatingInput v-model.number="warrantyCoveredAmount" type="number" min="0" :max="total" step="1000" label="Chi phí được bảo hành (VNĐ)" id="repair_warranty_covered" @input="coverageTouched = true" />
            </div>
            <div class="space-y-1 rounded-xl border border-emerald-100 bg-emerald-50 p-3 text-sm">
                <div class="flex justify-between"><span>Tiền linh kiện</span><strong>{{ money(totalParts) }}</strong></div>
                <div class="flex justify-between"><span>Công sửa + phụ phí</span><strong>{{ money(Number(laborCost || 0) + Number(surcharge || 0)) }}</strong></div>
                <div v-if="isWarrantyBilling" class="flex justify-between"><span>Tổng chi phí sửa</span><strong>{{ money(total) }}</strong></div>
                <div v-if="isWarrantyBilling" class="flex justify-between text-emerald-700"><span>Được bảo hành</span><strong>− {{ money(coveredAmount) }}</strong></div>
                <div class="flex justify-between border-t border-emerald-200 pt-2 text-base font-bold text-emerald-800"><span>{{ isWarrantyBilling ? 'Khách cần thanh toán' : 'Thành tiền' }}</span><span>{{ money(payableTotal) }}</span></div>
            </div>
        </div>
        <div v-if="showSubmit" class="mt-4 flex justify-end">
            <ActionButton :disabled="saving" @click="submit">{{ saving ? 'Đang lưu...' : 'Hoàn tất & thanh toán' }}</ActionButton>
        </div>
    </section>
</template>
