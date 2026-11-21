<script setup>
import { computed } from 'vue'
import BaseModal from '@/Components/UI/BaseModal.vue'
import { formatMoney } from '@/utils/format'

/*
 * Chi tiết một lần thanh toán công nợ (khách hàng: thu | nhà cung cấp: chi):
 * hình thức, số tiền thu/chi, giảm trừ, tiền khách đưa, thừa / thiếu, nợ trước - sau và từng hóa đơn / phiếu được trả.
 */
const props = defineProps({
    entity: { type: String, required: true }, // 'customer' | 'supplier'
    item: { type: Object, required: true },
})
const emit = defineEmits(['close', 'open-order'])

const isCustomer = computed(() => props.entity === 'customer')
const methodLabel = computed(() => ({ cash: 'Tiền mặt', bank: 'Chuyển khoản', card: 'Thẻ', discount: 'Chỉ giảm trừ' }[props.item.method] || props.item.method || '—'))
const verb = computed(() => (isCustomer.value ? 'thu' : 'chi'))
const title = computed(() => (isCustomer.value ? 'Chi tiết thu công nợ' : 'Chi tiết thanh toán công nợ'))
const hasDetail = computed(() => props.item.has_detail && props.item.received !== null)

const receivedLabel = computed(() => {
    if (props.item.method === 'bank') return isCustomer.value ? 'Tiền khách đã chuyển' : 'Tiền đã chuyển'
    if (props.item.method === 'card') return 'Số tiền quẹt thẻ'
    return isCustomer.value ? 'Tiền khách đưa' : 'Tiền đưa nhà cung cấp'
})
const payable = computed(() => Number(props.item.amount || 0) + Number(props.item.shortage || 0))

// Các dòng tóm tắt, chỉ hiện dòng có ý nghĩa.
const lines = computed(() => {
    const item = props.item
    const rows = []
    if (hasDetail.value && item.debt_before !== null) rows.push({ label: 'Nợ trước khi thanh toán', value: formatMoney(item.debt_before) })
    if (item.discount > 0) rows.push({ label: 'Giảm trừ', value: `− ${formatMoney(item.discount)}`, tone: 'text-emerald-700' })
    if (hasDetail.value) rows.push({ label: 'Cần thanh toán', value: formatMoney(payable.value) })
    if (hasDetail.value && item.method !== 'discount') rows.push({ label: receivedLabel.value, value: formatMoney(item.received) })
    rows.push({ label: isCustomer.value ? 'Thực thu' : 'Thực chi', value: formatMoney(item.amount), strong: true, tone: 'text-blue-700' })
    return rows
})

const status = computed(() => {
    const item = props.item
    if (!hasDetail.value) return null
    if (item.change > 0) return { tone: 'border-emerald-200 bg-emerald-50 text-emerald-700', title: isCustomer.value ? 'Tiền thừa trả khách' : 'Tiền thừa (thu lại)', amount: item.change }
    if (item.shortage > 0) return { tone: 'border-amber-200 bg-amber-50 text-amber-800', title: 'Còn thiếu (giữ lại trong công nợ)', amount: item.shortage }
    return { tone: 'border-blue-200 bg-blue-50 text-blue-700', title: 'Đã đủ tiền', amount: null }
})
</script>

<template>
    <BaseModal :title="title" size="md" layer-class="z-[1010]" body-class="p-4 sm:p-5" @close="emit('close')">
        <div class="space-y-3">
            <div class="rounded-xl border border-slate-200 bg-slate-50/60 px-3.5 py-3">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="truncate text-sm font-bold text-slate-900">{{ item.party || '—' }}</div>
                        <div v-if="item.party_code" class="text-[11px] text-slate-500">{{ item.party_code }}</div>
                    </div>
                    <span class="shrink-0 rounded-full border border-blue-200 bg-blue-50 px-2.5 py-1 text-[11px] font-bold text-blue-700">{{ methodLabel }}</span>
                </div>
                <dl class="mt-2 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-xs">
                    <dt class="text-slate-500">Thời gian</dt><dd class="text-right font-semibold text-slate-700">{{ item.date_full || item.date }}</dd>
                    <template v-if="item.user"><dt class="text-slate-500">Người thực hiện</dt><dd class="text-right font-semibold text-slate-700">{{ item.user }}</dd></template>
                </dl>
            </div>

            <div class="rounded-xl border border-slate-200 px-3.5 py-3">
                <dl class="space-y-1.5 text-sm">
                    <div v-for="line in lines" :key="line.label" class="flex items-baseline justify-between gap-3">
                        <dt :class="line.strong ? 'font-bold text-slate-800' : 'text-slate-600'">{{ line.label }}</dt>
                        <dd class="font-bold" :class="[line.tone || 'text-slate-900', line.strong ? 'text-lg font-black' : '']">{{ line.value }}</dd>
                    </div>
                </dl>
            </div>

            <div v-if="status" class="flex items-center justify-between gap-3 rounded-xl border px-3.5 py-2.5" :class="status.tone">
                <span class="text-xs font-bold">{{ status.title }}</span>
                <b v-if="status.amount !== null" class="text-lg font-black">{{ formatMoney(status.amount) }}</b>
            </div>

            <div v-if="hasDetail && item.debt_after !== null" class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm">
                <span class="text-slate-600">Nợ còn lại sau thanh toán</span>
                <b class="font-black text-amber-600">{{ formatMoney(item.debt_after) }}</b>
            </div>
            <p v-else-if="!hasDetail" class="rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-xs text-slate-500">
                Giao dịch này được ghi trước khi hệ thống lưu chi tiết nên không có số tiền đưa, thừa, thiếu và nợ trước / sau.
            </p>

            <div v-if="item.orders?.length" class="overflow-hidden rounded-xl border border-slate-200">
                <div class="border-b border-slate-100 bg-slate-50/70 px-3.5 py-2 text-[11px] font-extrabold uppercase tracking-wide text-slate-500">
                    {{ isCustomer ? 'Hóa đơn được thanh toán' : 'Phiếu nhập được thanh toán' }}
                </div>
                <ul class="divide-y divide-slate-100">
                    <li v-for="(order, index) in item.orders" :key="order.id || `general-${index}`" class="flex items-center justify-between gap-3 px-3.5 py-2.5">
                        <div class="min-w-0">
                            <button v-if="order.id" type="button" class="font-mono text-xs font-bold text-blue-700 hover:underline" @click="emit('open-order', order.id)">{{ order.code }}</button>
                            <span v-else class="text-xs font-semibold text-slate-600">Trừ thẳng vào công nợ tổng</span>
                            <div v-if="order.total !== null && order.id" class="text-[11px] text-slate-500">Tổng đơn {{ formatMoney(order.total) }}</div>
                        </div>
                        <div class="shrink-0 text-right">
                            <div v-if="order.amount > 0" class="text-sm font-bold text-emerald-700">{{ formatMoney(order.amount) }}</div>
                            <div v-if="order.discount > 0" class="text-[11px] font-semibold text-slate-500">Giảm trừ {{ formatMoney(order.discount) }}</div>
                        </div>
                    </li>
                </ul>
            </div>

            <div v-if="item.note" class="rounded-xl border border-slate-200 px-3.5 py-2.5 text-xs">
                <div class="mb-0.5 font-bold text-slate-500">Ghi chú</div>
                <p class="whitespace-pre-line text-slate-700">{{ item.note }}</p>
            </div>
        </div>

        <template #footer>
            <div class="flex justify-end">
                <button type="button" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50" @click="emit('close')">Đóng</button>
            </div>
        </template>
    </BaseModal>
</template>
