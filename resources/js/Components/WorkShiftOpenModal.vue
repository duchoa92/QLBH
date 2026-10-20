<script setup>
import axios from 'axios'
import { ref } from 'vue'
import { toast } from 'vue-sonner'
import WorkShiftCountForm from '@/Components/WorkShiftCountForm.vue'

const props = defineProps({
    denominations: { type: Array, required: true },
    counts: { type: Object, required: true },
    balances: { type: Object, required: true },
    accounts: { type: Array, default: () => [] },
    money: { type: Function, required: true },
})
const emit = defineEmits(['close', 'opened'])
const saving = ref(false)
const cashAmount = ref(0)
const cashDetail = ref(false)

const dismiss = async () => {
    try {
        await axios.post(route('pos.shifts.dismiss-prompt'))
        emit('close')
    } catch (error) {
        toast.error('Không thể lưu trạng thái đóng bảng kê. Vui lòng thử lại.')
    }
}

const startShift = async () => {
    saving.value = true
    try {
        const denominations = Object.fromEntries(props.denominations.map(value => [value, Math.max(0, Math.floor(Number(props.counts[value] || 0)))]))
        const { data } = await axios.post(route('pos.shifts.open'), {
            denominations,
            cash_amount: cashDetail.value ? null : Math.max(0, Number(String(cashAmount.value).replace(/\D/g, '') || 0)),
            bank_balances: props.balances,
        })
        emit('opened', data.shift)
    } catch (error) {
        toast.error(error.response?.data?.message || 'Không thể mở ca làm.')
    } finally {
        saving.value = false
    }
}
</script>

<template>
    <div class="fixed inset-0 z-[90] flex items-center justify-center bg-slate-950/50 p-4">
        <section class="flex max-h-[84vh] w-full max-w-4xl flex-col rounded-2xl bg-white p-4 shadow-2xl sm:p-5">
            <div class="flex items-start justify-between gap-3">
                <div><h2 class="text-lg font-bold text-slate-900">Bảng kê tiền đầu ca</h2><p class="mt-0.5 text-xs text-slate-500">Nhập số lượng tiền mặt và số dư tài khoản để bắt đầu ca.</p></div>
                <button type="button" class="rounded-lg px-3 py-1.5 text-sm font-medium text-slate-500 hover:bg-slate-100" @click="dismiss">Đóng</button>
            </div>
            <div class="custom-scrollbar mt-3 min-h-0 flex-1 overflow-y-auto px-1">
                <WorkShiftCountForm :denominations="denominations" :counts="counts" :cash-amount="cashAmount" :cash-detail="cashDetail" :balances="balances" :accounts="accounts" :money="money" @update:cash-amount="cashAmount = $event" @update:cash-detail="cashDetail = $event" />
            </div>
            <div class="mt-3 flex shrink-0 justify-end border-t border-slate-100 pt-3">
                <button :disabled="saving" class="rounded-xl bg-indigo-600 px-5 py-2 text-sm font-semibold text-white disabled:opacity-50" @click="startShift">{{ saving ? 'Đang lưu…' : 'Bắt đầu ca' }}</button>
            </div>
        </section>
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
.custom-scrollbar { scrollbar-width: thin; scrollbar-color: #cbd5e1 transparent; }
</style>
