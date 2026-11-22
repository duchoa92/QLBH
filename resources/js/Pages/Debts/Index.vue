<script setup>
import { computed, ref, watch } from 'vue'
import { HandCoins, Wallet, Users, Truck, ChevronDown, History } from 'lucide-vue-next'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageHeader from '@/Components/UI/PageHeader.vue'
import ActionButton from '@/Components/UI/ActionButton.vue'
import DebtPaymentModal from '@/Pages/Debts/DebtPaymentModal.vue'
import DebtPaymentDetailModal from '@/Pages/Debts/DebtPaymentDetailModal.vue'
import SaleSearchModal from '@/Components/Search/SaleSearchModal.vue'
import StockImportDetailModal from '@/Pages/StockImports/StockImportDetailModal.vue'
import { formatMoney } from '@/utils/format'
import ListToolbar from '@/Components/UI/ListToolbar.vue'
import ListFilterSelect from '@/Components/UI/ListFilterSelect.vue'
import PaginationBar from '@/Components/UI/PaginationBar.vue'
import HighlightText from '@/Components/UI/HighlightText.vue'
import { useLiveList } from '@/Composables/useLiveList'
import { useUrlView } from '@/Composables/useUrlState'

defineOptions({ layout: AdminLayout })

const props = defineProps({
    entity: { type: String, required: true },
    rows: { type: Object, required: true },
    filters: { type: Object, default: () => ({ search: '' }) },
    summary: { type: Object, required: true },
    orders: { type: Array, default: () => [] },
    history: { type: Array, default: () => [] },
    bank: { type: Object, default: () => ({ accounts: [], qr_theme: 'classic' }) },
})

const isCustomer = computed(() => props.entity === 'customer')

// Xem hóa đơn / phiếu nhập ngay tại trang này, không điều hướng.
const viewOrderId = ref(null)
const openOrder = (id) => { if (id) viewOrderId.value = id }
const title = computed(() => isCustomer.value ? 'Thu công nợ khách hàng' : 'Thanh toán công nợ nhà cung cấp')
const search = ref(props.filters.search || '')
const level = ref(props.filters.level || '')
const levelOptions = [
    { value: '', label: 'Mọi mức nợ' },
    { value: 'lt1m', label: 'Dưới 1 triệu' },
    { value: '1m_5m', label: '1 – 5 triệu' },
    { value: '5m_20m', label: '5 – 20 triệu' },
    { value: 'gt20m', label: 'Trên 20 triệu' },
]
const payModal = ref(null) // { party, orderId }
const historyDetail = ref(null) // một lần thanh toán đang xem chi tiết
const expandedId = ref(null)

// Tìm kiếm bằng AJAX, không tải lại trang; kết quả được bôi vàng.
const list = useLiveList({
    props,
    dataKey: 'rows',
    channels: ['debts', 'customers', 'suppliers', 'sales', 'stock_imports'],
    extraKeys: ['orders'],
    params: () => ({ search: search.value.trim(), level: level.value }),
})
const { data: rowsData, filters: filtersData, extras, loading } = list
const ordersData = extras.orders
watch(search, () => list.schedule())
watch(level, () => list.request())

// URL: ?view=order:ID (xem hóa đơn/phiếu nhập) | pay:ID (ghi nhận thu / thanh toán) | open:ID (mở danh sách đơn nợ)
useUrlView({
    key: 'view',
    current: () => {
        if (viewOrderId.value) return `order:${viewOrderId.value}`
        if (payModal.value) return `pay:${payModal.value.party.id}`
        if (expandedId.value) return `open:${expandedId.value}`
        return null
    },
    restore: (value) => {
        const [kind, id] = String(value || '').split(':')
        const row = id ? rowsData.value?.data?.find(item => String(item.id) === id) : null
        viewOrderId.value = null
        payModal.value = null
        expandedId.value = null
        if (kind === 'order' && id) viewOrderId.value = id
        else if (kind === 'pay' && row) pay(row)
        else if (kind === 'open' && row) expandedId.value = row.id
    },
})
const nameOf = row => isCustomer.value ? row.full_name : row.name
// Mở cửa sổ thanh toán: mặc định chọn tất cả hóa đơn/phiếu còn nợ; bấm "Thanh toán đơn này" thì chỉ chọn đơn đó.
const pay = (row, order = null) => {
    payModal.value = { party: row, orderId: order?.id || '' }
}
const ordersOf = party => ordersData.value.filter(item => Number(item.party_id) === Number(party.id))
</script>

<template>
    <div class="space-y-3">
        <PageHeader :title="title" :description="isCustomer ? 'Theo dõi số dư và ghi nhận các lần khách trả nợ.' : 'Theo dõi số dư phải trả và ghi nhận các lần thanh toán nhà cung cấp.'" />

        <div class="grid gap-3 sm:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-center gap-3">
                    <span class="rounded-xl bg-amber-50 p-3 text-amber-600"><Wallet :size="20" /></span>
                    <div><div class="text-xs font-semibold text-slate-500">{{ isCustomer ? 'Tổng khách hàng còn nợ' : 'Tổng công nợ phải trả' }}</div><div class="mt-1 text-xl font-black text-slate-900">{{ formatMoney(summary.total) }}</div></div>
                </div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-center gap-3">
                    <span class="rounded-xl bg-blue-50 p-3 text-blue-600"><component :is="isCustomer ? Users : Truck" :size="20" /></span>
                    <div><div class="text-xs font-semibold text-slate-500">{{ isCustomer ? 'Khách hàng đang nợ' : 'Nhà cung cấp còn công nợ' }}</div><div class="mt-1 text-xl font-black text-slate-900">{{ summary.count }}</div></div>
                </div>
            </div>
        </div>

        <ListToolbar v-model="search" :loading="loading" @submit="list.request()" :placeholder="isCustomer ? 'Tìm tên, mã hoặc số điện thoại...' : 'Tìm nhà cung cấp, mã hoặc số điện thoại...'">
            <template #filters>
                <ListFilterSelect v-model="level" label="Mức nợ" :show-placeholder="false" :options="levelOptions" />
            </template>
        </ListToolbar>
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="m-cards app-data-table w-full min-w-[720px] border-collapse text-left text-xs">
                    <thead><tr class="select-none border-b border-slate-200 bg-slate-50/80 text-[11px] font-extrabold uppercase tracking-wider text-slate-500"><th class="px-3.5 py-2.5">{{ isCustomer ? 'Khách Hàng' : 'Nhà Cung Cấp' }}</th><th class="px-3.5 py-2.5">Mã</th><th class="px-3.5 py-2.5">Điện Thoại</th><th class="px-3.5 py-2.5 text-right">Còn Nợ</th><th class="px-2 py-2.5 text-center">Thao Tác</th></tr></thead>
                    <tbody class="divide-y divide-slate-100 text-xs font-medium">
                        <template v-for="row in rowsData.data" :key="row.id">
                            <tr class="transition-colors hover:bg-emerald-50/70"><td class="px-3.5 py-2.5 font-bold text-slate-900"><HighlightText :text="nameOf(row)" :keyword="search" /></td><td class="px-3.5 py-2.5 text-slate-600"><HighlightText v-if="row.code" :text="row.code" :keyword="search" /><template v-else>—</template></td><td class="px-3.5 py-2.5 text-slate-600"><HighlightText v-if="row.phone" :text="row.phone" :keyword="search" /><template v-else>—</template></td><td class="px-3.5 py-2.5 text-right text-sm font-black text-rose-600">{{ formatMoney(row.debt_balance) }}</td><td class="px-3.5 py-2.5 text-center"><div class="inline-flex items-center justify-center gap-1"><button type="button" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="expandedId = expandedId === row.id ? null : row.id"><ChevronDown :size="14" :class="expandedId === row.id ? 'rotate-180' : ''" />Đơn nợ</button><ActionButton @click="pay(row)"><HandCoins :size="15" />{{ isCustomer ? 'Ghi nhận thu' : 'Thanh toán' }}</ActionButton></div></td></tr>
                            <tr v-if="expandedId === row.id"><td colspan="5" class="bg-slate-50 px-4 py-3"><div class="space-y-2"><div class="text-xs font-bold uppercase tracking-wide text-slate-500">Các {{ isCustomer ? 'hóa đơn' : 'phiếu nhập' }} còn nợ</div><div v-for="order in orders.filter(item => Number(item.party_id) === Number(row.id)).reverse()" :key="order.id" class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-200 bg-white p-3"><div class="min-w-[220px]"><button type="button" class="font-bold text-blue-700 hover:underline" @click="openOrder(order.id)">{{ order.code }}</button><div class="mt-1 text-xs text-slate-500">{{ order.date }} · Tổng {{ formatMoney(order.total) }} · Đã thanh toán {{ formatMoney(order.paid) }}</div></div><div class="flex items-center gap-3"><span class="text-sm font-bold text-rose-600">Còn {{ formatMoney(order.due) }}</span><button type="button" class="rounded-lg bg-blue-600 px-3 py-2 text-xs font-bold text-white hover:bg-blue-700" @click="pay(row, order)">Thanh toán đơn này</button></div></div><p v-if="!orders.some(item => Number(item.party_id) === Number(row.id))" class="text-sm text-slate-500">Chưa có hóa đơn/phiếu nhập nợ riêng để đối chiếu. Có thể ghi nhận khoản thanh toán tổng bằng nút phía trên.</p></div></td></tr>
                        </template>
                        <tr v-if="!rowsData.data.length"><td colspan="5" class="px-4 py-12 text-center text-slate-500">{{ search || level ? 'Không tìm thấy kết quả phù hợp.' : 'Hiện không có công nợ.' }}</td></tr>
                    </tbody>
                </table>
            </div>
            <PaginationBar :data="rowsData" :filters="filtersData" />
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center gap-2 border-b border-slate-100 p-4"><History :size="18" class="text-blue-600" /><div><h2 class="font-bold text-slate-900">Lịch sử thanh toán gần đây</h2><p class="mt-1 text-xs text-slate-500">Hiển thị tối đa 100 lần thu/chi mới nhất. Bấm vào một dòng để xem chi tiết.</p></div></div>
            <div class="overflow-x-auto"><table class="m-cards app-data-table w-full min-w-[760px] border-collapse text-left text-xs"><thead><tr class="select-none border-b border-slate-200 bg-slate-50/80 text-[11px] font-extrabold uppercase tracking-wider text-slate-500"><th class="px-4 py-3">Ngày giờ</th><th class="px-4 py-3">{{ isCustomer ? 'Khách hàng' : 'Nhà cung cấp' }}</th><th class="px-4 py-3">Đơn liên quan</th><th class="px-4 py-3">Hình thức</th><th class="px-4 py-3">Ghi chú</th><th class="px-4 py-3 text-right">Số tiền</th></tr></thead><tbody class="divide-y divide-slate-100 text-xs font-medium"><tr v-for="item in history" :key="item.id" class="cursor-pointer transition-colors hover:bg-emerald-50/70" tabindex="0" @click="historyDetail = item" @keydown.enter="historyDetail = item"><td class="px-4 py-3 text-slate-600">{{ item.date }}</td><td class="px-4 py-3 font-semibold">{{ item.party || '—' }}</td><td class="px-4 py-3"><button v-if="item.code && item.source_id" type="button" class="font-semibold text-blue-700 hover:underline" @click.stop="openOrder(item.source_id)">{{ item.code }}</button><span v-if="item.order_count > 1" class="ml-1 rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-500">+{{ item.order_count - 1 }} đơn</span><span v-else class="text-slate-400">{{ item.code || 'Thanh toán tổng' }}</span></td><td class="px-4 py-3 text-slate-600">{{ { cash: 'Tiền mặt', bank: 'Chuyển khoản', card: 'Thẻ', discount: 'Giảm trừ' }[item.method] || item.method }}</td><td class="max-w-[220px] truncate px-4 py-3 text-slate-500">{{ item.note || '—' }}</td><td class="px-4 py-3 text-right"><div class="font-bold text-emerald-700">{{ formatMoney(item.amount > 0 ? item.amount : item.discount) }}</div><div v-if="item.amount > 0 && item.discount > 0" class="text-[10px] font-semibold text-slate-500">Giảm {{ formatMoney(item.discount) }}</div><div v-else-if="item.amount <= 0" class="text-[10px] font-semibold text-slate-500">Giảm trừ</div></td></tr><tr v-if="!history.length"><td colspan="6" class="px-4 py-8 text-center text-slate-500">Chưa có lịch sử thanh toán.</td></tr></tbody></table></div>
        </div>

        <DebtPaymentModal
            v-if="payModal"
            :key="`${payModal.party.id}-${payModal.orderId}`"
            :entity="entity"
            :party="payModal.party"
            :orders="ordersOf(payModal.party)"
            :initial-order-id="payModal.orderId"
            :bank="bank"
            @close="payModal = null"
        />

        <DebtPaymentDetailModal
            v-if="historyDetail"
            :entity="entity"
            :item="historyDetail"
            @close="historyDetail = null"
            @open-order="(id) => { historyDetail = null; openOrder(id) }"
        />

        <template v-if="viewOrderId">
            <SaleSearchModal v-if="isCustomer" :sale-id="viewOrderId" @close="viewOrderId = null" />
            <StockImportDetailModal v-else :import-id="viewOrderId" @close="viewOrderId = null" />
        </template>
    </div>
</template>
