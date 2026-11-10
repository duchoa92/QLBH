<script setup>
// Danh sách hóa đơn bán / phiếu nhập gần đây: mã (bấm mở), ngày, tổng tiền, trạng thái, tiến độ thanh toán.
import { formatMoney } from '@/utils/format'

defineProps({
    rows: { type: Array, default: () => [] },
    kind: { type: String, default: 'sale' }, // sale | import
    emptyText: { type: String, default: 'Chưa có giao dịch.' },
})

const statusMap = {
    sale: { completed: ['Hoàn tất', 'bg-emerald-50 text-emerald-700'], unpaid: ['Chưa thanh toán', 'bg-amber-50 text-amber-700'], cancelled: ['Đã hủy', 'bg-slate-100 text-slate-500'] },
    import: { completed: ['Đã nhập', 'bg-emerald-50 text-emerald-700'], draft: ['Nháp', 'bg-amber-50 text-amber-700'], cancelled: ['Đã hủy', 'bg-slate-100 text-slate-500'] },
}
const statusOf = (kind, status) => statusMap[kind]?.[status] || [status || '—', 'bg-slate-100 text-slate-600']
const percentPaid = row => row.total > 0 ? Math.min(100, Math.round((row.paid / row.total) * 100)) : 100
</script>

<template>
    <ul v-if="rows.length" class="divide-y divide-slate-100 overflow-hidden rounded-xl border border-slate-200 bg-white">
        <li v-for="row in rows" :key="row.id" class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <a :href="row.href" class="text-sm font-bold text-blue-700 hover:underline">{{ row.code }}</a>
                    <span class="rounded-full px-2 py-0.5 text-[10px] font-bold" :class="statusOf(kind, row.status)[1]">{{ statusOf(kind, row.status)[0] }}</span>
                </div>
                <div class="mt-0.5 text-xs text-slate-500">{{ row.date }}<template v-if="row.customer"> · {{ row.customer }}</template></div>
            </div>
            <div class="text-right">
                <div class="text-sm font-black text-slate-900">{{ formatMoney(row.total) }}</div>
                <div v-if="row.due > 0" class="text-xs font-semibold text-rose-600">Còn nợ {{ formatMoney(row.due) }}</div>
                <div v-else class="text-xs font-semibold text-emerald-600">Đã thanh toán đủ</div>
                <div v-if="row.due > 0" class="mt-1 h-1 w-28 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-emerald-500" :style="{ width: percentPaid(row) + '%' }"></div></div>
            </div>
        </li>
    </ul>
    <div v-else class="rounded-xl border border-dashed border-slate-200 py-8 text-center text-sm text-slate-500">{{ emptyText }}</div>
</template>
