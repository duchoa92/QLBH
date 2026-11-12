<script setup>
import { ref } from 'vue'
import { CheckCircle2, Landmark, Banknote, CreditCard, Receipt, X } from 'lucide-vue-next'
import BaseModal from '@/Components/UI/BaseModal.vue'
import { formatCurrency } from '@/utils/format'

// Thanh toán cho khách lẻ khi cửa hàng mua lại hàng. Khác nhà cung cấp, khách lẻ không có công nợ:
// phiếu mua lại luôn được chi trả đủ ngay, nên chỉ cần chọn hình thức chi tiền.
const props = defineProps({
    customer: { type: Object, required: true },
    total: { type: Number, required: true },
    editMode: { type: Boolean, default: false },
    initialPayment: { type: Object, default: () => ({}) },
})
const emit = defineEmits(['close', 'updated'])

const methods = [
    { value: 'cash', label: 'Tiền mặt', icon: Banknote },
    { value: 'bank', label: 'Chuyển khoản', icon: Landmark },
    { value: 'card', label: 'Thẻ / POS', icon: CreditCard },
]
const paymentMethod = ref(['cash', 'bank', 'card'].includes(props.initialPayment.payment_method) ? props.initialPayment.payment_method : 'cash')

const submit = () => {
    const amount = Math.max(0, Number(props.total || 0))
    emit('updated', {
        payment_method: paymentMethod.value,
        paid_amount: amount,
        tendered_amount: amount,
        change_amount: 0,
    })
    emit('close')
}
</script>

<template>
    <BaseModal title="Xác nhận thanh toán" size="md" layer-class="z-[1200]" body-class="space-y-4 p-4 sm:p-5" @close="emit('close')">
        <template #header>
            <div class="flex items-center gap-3">
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600"><Receipt class="h-5 w-5" /></div>
                <div>
                    <div class="text-base font-bold text-slate-900">{{ editMode ? 'Cập nhật thanh toán' : 'Xác nhận thanh toán' }}</div>
                    <div class="text-xs font-medium text-slate-500">{{ editMode ? 'Điều chỉnh khoản chi cho khách' : 'Chi trả cho khách khi mua lại hàng' }}</div>
                </div>
            </div>
            <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-white hover:text-slate-600" aria-label="Đóng" @click="emit('close')"><X class="h-5 w-5" /></button>
        </template>

        <div class="flex min-w-0 items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm">
            <div class="min-w-0">
                <div class="truncate font-bold text-slate-800">{{ customer.full_name || 'Khách lẻ' }}</div>
                <div class="text-xs text-slate-500">{{ customer.phone || 'Chưa có SĐT' }}</div>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-2">
            <button
                v-for="method in methods"
                :key="method.value"
                type="button"
                class="flex flex-col items-center justify-center gap-1.5 rounded-xl border py-3 text-xs font-bold transition"
                :class="paymentMethod === method.value ? 'border-blue-600 bg-blue-50 text-blue-600' : 'border-slate-200 text-slate-600 hover:bg-slate-50'"
                @click="paymentMethod = method.value"
            >
                <component :is="method.icon" class="h-4 w-4" />{{ method.label }}
            </button>
        </div>

        <div class="space-y-2 rounded-xl border border-slate-200 bg-slate-50/70 p-3 text-sm">
            <div class="flex justify-between border-slate-200 text-base font-extrabold text-blue-700">
                <span>Cần trả khách</span><span>{{ formatCurrency(total) }}</span>
            </div>
            <div class="flex justify-between border-t border-slate-200 pt-2 text-xs font-bold text-emerald-600">
                <span>Trả đủ bằng {{ methods.find(method => method.value === paymentMethod)?.label.toLowerCase() }}</span><span>✓</span>
            </div>
        </div>

        <template #footer>
            <div class="flex gap-2">
                <button type="button" class="flex-1 rounded-xl border border-slate-200 bg-white py-3 text-xs font-bold text-slate-600 hover:bg-slate-50" @click="emit('close')">Hủy bỏ</button>
                <button type="button" class="flex-[2] inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 py-3 text-xs font-bold text-white shadow-md shadow-emerald-200 hover:bg-emerald-700" @click="submit">
                    <CheckCircle2 class="h-4 w-4" />Xác nhận thanh toán
                </button>
            </div>
        </template>
    </BaseModal>
</template>
