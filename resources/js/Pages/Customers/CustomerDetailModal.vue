<script setup>
import { computed, ref } from 'vue'
import { Cake, CreditCard, Mail, MapPin, Phone, ShoppingBag, Smartphone, Star, StickyNote, Wallet } from 'lucide-vue-next'
import { formatMoney } from '@/utils/format'
import { useRecordDetail } from '@/Composables/useRecordDetail'
import SearchHighlight from '@/Components/UI/SearchHighlight.vue'
import DetailShell from '@/Components/Detail/DetailShell.vue'
import DetailAvatar from '@/Components/Detail/DetailAvatar.vue'
import DetailTabs from '@/Components/Detail/DetailTabs.vue'
import StatCard from '@/Components/Detail/StatCard.vue'
import InfoItem from '@/Components/Detail/InfoItem.vue'
import TransactionList from '@/Components/Detail/TransactionList.vue'
import DebtLedger from '@/Components/Detail/DebtLedger.vue'

const props = defineProps({
    recordId: { type: [Number, String], required: true },
    keyword: { type: String, default: '' },
    initialTab: { type: String, default: 'overview' },
    canPay: { type: Boolean, default: false },
    payHandler: { type: Function, default: null },
    modalId: { type: [Number, String], default: null },
})
const emit = defineEmits(['close'])

const { data, loading, error } = useRecordDetail('customer', props.recordId)
const tab = ref(props.initialTab)

const typeLabels = { retail: 'Khách lẻ', wholesale: 'Khách sỉ', vip: 'Khách VIP' }
const genderLabels = { male: 'Nam', female: 'Nữ', other: 'Khác' }

const tabs = computed(() => {
    if (!data.value) return []
    const list = [{ key: 'overview', label: 'Tổng quan' }]
    if (data.value.sales) list.push({ key: 'sales', label: 'Giao dịch', count: data.value.sales.length })
    if (data.value.debt) list.push({ key: 'debt', label: 'Công nợ', count: data.value.debt.orders.length, tone: 'danger' })
    list.push({ key: 'points', label: 'Điểm', count: data.value.points.length })
    list.push({ key: 'devices', label: 'Thiết bị', count: data.value.devices.length })
    return list
})
const activeTab = computed(() => tabs.value.some(item => item.key === tab.value) ? tab.value : 'overview')

const owing = computed(() => (data.value?.stats.debt_balance || 0) > 0)
const personal = computed(() => [data.value?.birthday, genderLabels[data.value?.gender]].filter(Boolean).join(' · '))

// Đóng modal chi tiết rồi mở form thu nợ của trang công nợ
const pay = (order) => {
    emit('close')
    props.payHandler?.(order)
}
</script>

<template>
    <DetailShell size="xl" :loading="loading" :error="error" @close="emit('close')">
        <template #header>
            <div class="flex items-center gap-4">
                <DetailAvatar :name="data?.name || ''" />
                <div class="min-w-0">
                    <h2 class="truncate text-lg font-black text-slate-900"><SearchHighlight :text="data?.name || 'Khách hàng'" :keyword="keyword" /></h2>
                    <div class="mt-1 flex flex-wrap items-center gap-1.5 text-xs">
                        <span v-if="data?.code" class="rounded-md bg-slate-100 px-2 py-0.5 font-bold text-slate-600"><SearchHighlight :text="data.code" :keyword="keyword" /></span>
                        <span v-if="data" class="rounded-md bg-blue-50 px-2 py-0.5 font-semibold text-blue-700">{{ typeLabels[data.type] || data.type || 'Khách hàng' }}</span>
                        <span v-if="data?.blocked" class="rounded-md bg-rose-100 px-2 py-0.5 font-bold text-rose-700">Đã chặn giao dịch</span>
                        <span v-else-if="owing" class="rounded-md bg-amber-100 px-2 py-0.5 font-bold text-amber-700">Đang còn nợ</span>
                    </div>
                </div>
            </div>
        </template>

        <template v-if="data" #stats>
            <StatCard label="Công nợ" :value="formatMoney(data.stats.debt_balance)" :tone="owing ? 'danger' : 'success'" :icon="Wallet" :hint="owing ? 'Cần thu' : 'Không còn nợ'" />
            <StatCard label="Tổng chi tiêu" :value="formatMoney(data.stats.total_spent)" tone="success" :icon="ShoppingBag" />
            <StatCard label="Số đơn hàng" :value="data.stats.total_orders" tone="info" :icon="ShoppingBag" :hint="data.stats.last_order_at ? 'Gần nhất ' + data.stats.last_order_at : 'Chưa mua lần nào'" />
            <StatCard label="Điểm tích lũy" :value="Number(data.stats.point_balance).toLocaleString('vi-VN')" tone="warning" :icon="Star" />
        </template>

        <template v-if="data" #tabs><DetailTabs v-model="tab" :tabs="tabs" /></template>

        <template v-if="data">
            <!-- TỔNG QUAN -->
            <template v-if="activeTab === 'overview'">
                <div class="grid gap-2.5 sm:grid-cols-2">
                    <InfoItem :icon="Phone" label="Điện thoại" :value="data.phone" :href="data.phone ? `tel:${data.phone}` : ''" />
                    <InfoItem :icon="Mail" label="Email" :value="data.email" :href="data.email ? `mailto:${data.email}` : ''" />
                    <InfoItem :icon="CreditCard" label="CCCD" :value="data.cccd" />
                    <InfoItem :icon="Cake" label="Ngày sinh / giới tính" :value="personal" />
                    <InfoItem class="sm:col-span-2" :icon="MapPin" label="Địa chỉ" :value="data.address" />
                </div>
                <div v-if="data.note" class="flex gap-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                    <StickyNote :size="18" class="mt-0.5 shrink-0" /><p class="whitespace-pre-line break-words">{{ data.note }}</p>
                </div>
                <p class="text-xs text-slate-400">Tạo ngày {{ data.created_at || '—' }}</p>
            </template>

            <!-- GIAO DỊCH -->
            <TransactionList v-else-if="activeTab === 'sales'" kind="sale" :rows="data.sales" empty-text="Khách này chưa có hóa đơn nào." />

            <!-- CÔNG NỢ -->
            <DebtLedger
                v-else-if="activeTab === 'debt'"
                entity="customer"
                :balance="data.stats.debt_balance"
                :orders="data.debt.orders"
                :history="data.debt.history"
                :can-pay="canPay"
                @pay="pay"
            />

            <!-- ĐIỂM -->
            <template v-else-if="activeTab === 'points'">
                <ul v-if="data.points.length" class="divide-y divide-slate-100 overflow-hidden rounded-xl border border-slate-200 bg-white">
                    <li v-for="point in data.points" :key="point.id" class="flex items-center justify-between gap-3 px-4 py-3">
                        <div class="min-w-0">
                            <div class="text-sm font-semibold text-slate-800">{{ point.type === 'earn' ? 'Tích điểm' : 'Đổi / trừ điểm' }}</div>
                            <div class="truncate text-xs text-slate-500">{{ point.date }}<template v-if="point.note"> · {{ point.note }}</template></div>
                        </div>
                        <span class="shrink-0 text-sm font-black" :class="point.type === 'earn' ? 'text-emerald-600' : 'text-amber-600'">{{ point.type === 'earn' ? '+' : '−' }}{{ Math.abs(point.points).toLocaleString('vi-VN') }}</span>
                    </li>
                </ul>
                <div v-else class="rounded-xl border border-dashed border-slate-200 py-8 text-center text-sm text-slate-500">Chưa có lịch sử điểm.</div>
            </template>

            <!-- THIẾT BỊ -->
            <template v-else-if="activeTab === 'devices'">
                <div v-if="data.devices.length" class="grid gap-2.5 sm:grid-cols-2">
                    <div v-for="device in data.devices" :key="device.id" class="flex items-start gap-3 rounded-xl border border-slate-200 bg-white p-3">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-slate-100 text-slate-500"><Smartphone :size="18" /></span>
                        <div class="min-w-0">
                            <div class="truncate text-sm font-bold text-slate-800">{{ device.name || 'Thiết bị' }}</div>
                            <div class="text-xs text-slate-500">IMEI: {{ device.imei || '—' }}<template v-if="device.color"> · {{ device.color }}</template></div>
                        </div>
                    </div>
                </div>
                <div v-else class="rounded-xl border border-dashed border-slate-200 py-8 text-center text-sm text-slate-500">Chưa lưu thiết bị nào của khách này.</div>
            </template>
        </template>
    </DetailShell>
</template>
