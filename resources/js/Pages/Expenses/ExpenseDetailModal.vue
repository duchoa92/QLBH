<script setup>
import { computed } from 'vue'
import { Banknote, Building2, CalendarDays, CreditCard, Landmark, Paperclip, Pencil, Receipt, StickyNote, Trash2, UserRound, Users, Wifi, Zap } from 'lucide-vue-next'
import { formatMoney } from '@/utils/format'
import BaseModal from '@/Components/UI/BaseModal.vue'
import InfoItem from '@/Components/Detail/InfoItem.vue'

const props = defineProps({
    expense: { type: Object, required: true },
    canManage: { type: Boolean, default: false },
    categoryLabels: { type: Object, default: () => ({}) },
    paymentLabels: { type: Object, default: () => ({}) },
})
const emit = defineEmits(['close', 'edit', 'delete'])

// Mỗi loại chi có biểu tượng và màu riêng để nhận ra nhanh
const looks = {
    utilities: { icon: Zap, tone: 'bg-amber-100 text-amber-700' },
    internet: { icon: Wifi, tone: 'bg-cyan-100 text-cyan-700' },
    rent: { icon: Building2, tone: 'bg-violet-100 text-violet-700' },
    tax: { icon: Landmark, tone: 'bg-rose-100 text-rose-700' },
    salary: { icon: Users, tone: 'bg-blue-100 text-blue-700' },
    supplies: { icon: Paperclip, tone: 'bg-emerald-100 text-emerald-700' },
    other: { icon: Receipt, tone: 'bg-slate-100 text-slate-600' },
}
const look = computed(() => looks[props.expense.category] || looks.other)
const paymentIcon = computed(() => props.expense.payment_method === 'cash' ? Banknote : props.expense.payment_method === 'bank' ? Landmark : CreditCard)

const formatDay = (value) => {
    const match = String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})/)
    return match ? `${match[3]}/${match[2]}/${match[1]}` : ''
}
const formatDateTime = value => value ? new Date(value).toLocaleString('vi-VN') : ''
const createdAt = computed(() => formatDateTime(props.expense.created_at))
const updatedAt = computed(() => props.expense.updated_at && props.expense.updated_at !== props.expense.created_at ? formatDateTime(props.expense.updated_at) : '')
</script>

<template>
    <BaseModal title="Chi tiết khoản chi" size="md" body-class="p-0" @close="emit('close')">
        <div class="flex flex-col items-center gap-2 border-b border-slate-100 bg-gradient-to-b from-slate-50 to-white px-5 py-6 text-center">
            <span class="grid h-14 w-14 place-items-center rounded-2xl" :class="look.tone"><component :is="look.icon" :size="26" /></span>
            <span class="rounded-full bg-white px-3 py-0.5 text-xs font-bold text-slate-600 ring-1 ring-slate-200">{{ categoryLabels[expense.category] || expense.category }}</span>
            <h2 class="max-w-full break-words text-base font-bold text-slate-800">{{ expense.description }}</h2>
            <div class="text-3xl font-black text-rose-600">{{ formatMoney(expense.amount) }}</div>
        </div>

        <div class="space-y-2.5 p-5">
            <div class="grid gap-2.5 sm:grid-cols-2">
                <InfoItem :icon="CalendarDays" label="Ngày chi" :value="formatDay(expense.expense_date)" />
                <InfoItem :icon="paymentIcon" label="Hình thức thanh toán" :value="paymentLabels[expense.payment_method] || expense.payment_method" />
                <InfoItem :icon="UserRound" label="Người nhận" :value="expense.recipient" />
                <InfoItem :icon="UserRound" label="Người ghi nhận" :value="expense.user?.name" />
            </div>
            <div v-if="expense.note" class="flex gap-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                <StickyNote :size="18" class="mt-0.5 shrink-0" /><p class="whitespace-pre-line break-words">{{ expense.note }}</p>
            </div>
            <p class="pt-1 text-xs text-slate-400">Ghi lúc {{ createdAt || '—' }}<template v-if="updatedAt"> · Sửa lần cuối {{ updatedAt }}</template></p>
        </div>

        <template v-if="canManage" #footer>
            <div class="flex justify-end gap-2">
                <button type="button" class="inline-flex items-center gap-1.5 rounded-xl border border-rose-200 bg-white px-4 py-2 text-sm font-semibold text-rose-600 transition hover:bg-rose-50" @click="emit('delete')"><Trash2 :size="15" /> Xóa</button>
                <button type="button" class="inline-flex items-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-blue-700" @click="emit('edit')"><Pencil :size="15" /> Sửa</button>
            </div>
        </template>
    </BaseModal>
</template>
