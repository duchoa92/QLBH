<script setup>
// Công nợ của một khách hàng / nhà cung cấp: số dư, các đơn còn nợ (kèm nút thu/trả) và lịch sử phát sinh.
import { ArrowDownLeft, ArrowUpRight, HandCoins } from 'lucide-vue-next'
import { formatMoney } from '@/utils/format'

const props = defineProps({
    entity: { type: String, default: 'customer' }, // customer | supplier
    balance: { type: Number, default: 0 },
    orders: { type: Array, default: () => [] },
    history: { type: Array, default: () => [] },
    canPay: { type: Boolean, default: false },
})
defineEmits(['pay'])

const isCustomer = props.entity === 'customer'
const orderWord = isCustomer ? 'hóa đơn' : 'phiếu nhập'
const payLabel = isCustomer ? 'Thu nợ' : 'Thanh toán'
const methodLabels = { cash: 'Tiền mặt', bank: 'Chuyển khoản', card: 'Thẻ' }
const percentPaid = order => order.total > 0 ? Math.min(100, Math.round((order.paid / order.total) * 100)) : 0
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border p-4" :class="balance > 0 ? 'border-rose-200 bg-rose-50/60' : 'border-emerald-200 bg-emerald-50/60'">
            <div>
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ isCustomer ? 'Khách đang nợ' : 'Cần trả nhà cung cấp' }}</div>
                <div class="mt-0.5 text-2xl font-black" :class="balance > 0 ? 'text-rose-700' : 'text-emerald-700'">{{ balance > 0 ? formatMoney(balance) : 'Không còn nợ' }}</div>
            </div>
            <button v-if="canPay && balance > 0" type="button" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700" @click="$emit('pay', null)">
                <HandCoins :size="16" /> {{ payLabel }}
            </button>
        </div>

        <section v-if="orders.length" class="space-y-2">
            <h3 class="text-xs font-bold uppercase tracking-wide text-slate-500">Các {{ orderWord }} còn nợ ({{ orders.length }})</h3>
            <div v-for="order in orders" :key="order.id" class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-3">
                <div class="min-w-[180px] flex-1">
                    <a :href="order.href" class="text-sm font-bold text-blue-700 hover:underline">{{ order.code }}</a>
                    <div class="mt-0.5 text-xs text-slate-500">{{ order.date }} · Tổng {{ formatMoney(order.total) }}</div>
                    <div class="mt-1.5 h-1.5 w-full max-w-[220px] overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-emerald-500" :style="{ width: percentPaid(order) + '%' }"></div></div>
                    <div class="mt-0.5 text-[11px] text-slate-500">Đã {{ isCustomer ? 'thu' : 'trả' }} {{ formatMoney(order.paid) }}</div>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-sm font-black text-rose-600">Còn {{ formatMoney(order.due) }}</span>
                    <button v-if="canPay" type="button" class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-bold text-blue-700 transition hover:bg-blue-100" @click="$emit('pay', order)">{{ payLabel }} đơn này</button>
                </div>
            </div>
        </section>
        <p v-else-if="balance > 0" class="rounded-xl border border-dashed border-slate-200 px-4 py-3 text-sm text-slate-500">
            Khoản nợ này không gắn với {{ orderWord }} cụ thể, có thể {{ isCustomer ? 'thu' : 'thanh toán' }} theo tổng số.
        </p>

        <section class="space-y-2">
            <h3 class="text-xs font-bold uppercase tracking-wide text-slate-500">Lịch sử công nợ</h3>
            <ul v-if="history.length" class="divide-y divide-slate-100 overflow-hidden rounded-xl border border-slate-200 bg-white">
                <li v-for="entry in history" :key="entry.id" class="flex items-start gap-3 px-4 py-3">
                    <span class="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-full" :class="entry.type === 'increase' ? 'bg-rose-100 text-rose-600' : 'bg-emerald-100 text-emerald-600'">
                        <ArrowUpRight v-if="entry.type === 'increase'" :size="16" /><ArrowDownLeft v-else :size="16" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="text-sm font-semibold text-slate-800">
                            {{ entry.type === 'increase' ? 'Phát sinh nợ' : (isCustomer ? 'Khách đã trả' : 'Đã thanh toán') }}
                            <span v-if="entry.code" class="font-normal text-slate-500">· {{ entry.code }}</span>
                        </div>
                        <div class="text-xs text-slate-500">
                            {{ entry.date }}<template v-if="entry.method"> · {{ methodLabels[entry.method] || entry.method }}</template><template v-if="entry.by"> · {{ entry.by }}</template>
                        </div>
                        <div v-if="entry.note" class="mt-0.5 text-xs text-slate-500">{{ entry.note }}</div>
                    </div>
                    <div class="shrink-0 text-sm font-black" :class="entry.type === 'increase' ? 'text-rose-600' : 'text-emerald-600'">
                        {{ entry.type === 'increase' ? '+' : '−' }}{{ formatMoney(entry.amount) }}
                    </div>
                </li>
            </ul>
            <div v-else class="rounded-xl border border-dashed border-slate-200 py-6 text-center text-sm text-slate-500">Chưa có lịch sử công nợ.</div>
        </section>
    </div>
</template>
