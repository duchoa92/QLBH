<script setup>
import { computed, ref } from 'vue'
import { AtSign, Banknote, CalendarDays, Clock, Mail, Phone, Receipt, ShieldCheck, TrendingUp } from 'lucide-vue-next'
import { formatMoney } from '@/utils/format'
import { useRecordDetail } from '@/Composables/useRecordDetail'
import SearchHighlight from '@/Components/UI/SearchHighlight.vue'
import DetailShell from '@/Components/Detail/DetailShell.vue'
import DetailAvatar from '@/Components/Detail/DetailAvatar.vue'
import DetailTabs from '@/Components/Detail/DetailTabs.vue'
import StatCard from '@/Components/Detail/StatCard.vue'
import InfoItem from '@/Components/Detail/InfoItem.vue'
import TransactionList from '@/Components/Detail/TransactionList.vue'

const props = defineProps({
    recordId: { type: [Number, String], required: true },
    keyword: { type: String, default: '' },
    modalId: { type: [Number, String], default: null },
})
const emit = defineEmits(['close'])

const { data, loading, error } = useRecordDetail('user', props.recordId)
const tab = ref('overview')

const groupLabels = {
    dashboard: 'Tổng quan', pos: 'Bán hàng (POS)', sales: 'Hóa đơn', products: 'Sản phẩm', categories: 'Danh mục', brands: 'Thương hiệu',
    customers: 'Khách hàng', suppliers: 'Nhà cung cấp', stock_imports: 'Nhập hàng', repairs: 'Sửa chữa', expenses: 'Chi phí',
    customer_debts: 'Công nợ khách hàng', supplier_debts: 'Công nợ nhà cung cấp', reports: 'Báo cáo', users: 'Người dùng', settings: 'Cài đặt',
}
const actionLabels = {
    view: 'Xem', create: 'Thêm', edit: 'Sửa', delete: 'Xóa', manage: 'Quản lý', pay: 'Thu/trả tiền', access: 'Truy cập', export: 'Xuất file', import: 'Nhập file',
}

const tabs = computed(() => {
    if (!data.value) return []
    const list = [{ key: 'overview', label: 'Tổng quan' }]
    if (data.value.sales || data.value.shifts) list.push({ key: 'activity', label: 'Hoạt động' })
    list.push({ key: 'permissions', label: 'Phân quyền', count: data.value.permission_count })
    return list
})
const activeTab = computed(() => tabs.value.some(item => item.key === tab.value) ? tab.value : 'overview')
</script>

<template>
    <DetailShell size="xl" :loading="loading" :error="error" @close="emit('close')">
        <template #header>
            <div class="flex items-center gap-4">
                <DetailAvatar :name="data?.name || ''" :image="data?.avatar || ''" />
                <div class="min-w-0">
                    <h2 class="truncate text-lg font-black text-slate-900"><SearchHighlight :text="data?.name || 'Nhân viên'" :keyword="keyword" /></h2>
                    <div class="mt-1 flex flex-wrap items-center gap-1.5 text-xs">
                        <span v-if="data?.username" class="rounded-md bg-slate-100 px-2 py-0.5 font-bold text-slate-600">@<SearchHighlight :text="data.username" :keyword="keyword" /></span>
                        <span v-for="role in data?.roles || []" :key="role" class="rounded-md bg-emerald-50 px-2 py-0.5 font-semibold text-emerald-700">{{ role }}</span>
                        <span v-if="data && !data.roles.length" class="rounded-md bg-amber-50 px-2 py-0.5 font-semibold text-amber-700">Chưa gán vai trò</span>
                    </div>
                </div>
            </div>
        </template>

        <template v-if="data?.stats" #stats>
            <StatCard label="Hóa đơn đã bán" :value="data.stats.sales_count" tone="info" :icon="Receipt" />
            <StatCard label="Doanh thu" :value="formatMoney(data.stats.revenue)" tone="success" :icon="Banknote" />
            <StatCard label="Doanh thu 30 ngày" :value="formatMoney(data.stats.revenue_30d)" tone="success" :icon="TrendingUp" />
            <StatCard label="Bán gần nhất" :value="data.stats.last_sale_at || 'Chưa có'" :icon="Clock" />
        </template>

        <template v-if="data" #tabs><DetailTabs v-model="tab" :tabs="tabs" /></template>

        <template v-if="data">
            <!-- TỔNG QUAN -->
            <template v-if="activeTab === 'overview'">
                <div class="grid gap-2.5 sm:grid-cols-2">
                    <InfoItem :icon="AtSign" label="Tên đăng nhập" :value="data.username" />
                    <InfoItem :icon="ShieldCheck" label="Vai trò" :value="data.roles.join(', ')" />
                    <InfoItem :icon="Phone" label="Điện thoại" :value="data.phone" :href="data.phone ? `tel:${data.phone}` : ''" />
                    <InfoItem :icon="Mail" label="Email" :value="data.email" :href="data.email ? `mailto:${data.email}` : ''" />
                    <InfoItem :icon="CalendarDays" label="Ngày tạo tài khoản" :value="data.created_at" />
                </div>
            </template>

            <!-- HOẠT ĐỘNG -->
            <template v-else-if="activeTab === 'activity'">
                <section v-if="data.sales" class="space-y-2">
                    <h3 class="text-xs font-bold uppercase tracking-wide text-slate-500">Hóa đơn bán gần đây</h3>
                    <TransactionList kind="sale" :rows="data.sales" empty-text="Nhân viên này chưa bán hóa đơn nào." />
                </section>

                <section v-if="data.shifts" class="space-y-2">
                    <h3 class="text-xs font-bold uppercase tracking-wide text-slate-500">Ca làm việc gần đây</h3>
                    <ul v-if="data.shifts.length" class="divide-y divide-slate-100 overflow-hidden rounded-xl border border-slate-200 bg-white">
                        <li v-for="shift in data.shifts" :key="shift.id" class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                            <div>
                                <div class="text-sm font-semibold text-slate-800">Mở ca {{ shift.opened_at }}</div>
                                <div class="text-xs text-slate-500">{{ shift.open ? 'Ca đang mở' : 'Đóng ca ' + shift.closed_at }}</div>
                            </div>
                            <div class="text-right text-xs text-slate-500">
                                <div>Tiền đầu ca <strong class="text-slate-800">{{ formatMoney(shift.opening_amount) }}</strong></div>
                                <div v-if="shift.closing_amount !== null">Tiền cuối ca <strong class="text-slate-800">{{ formatMoney(shift.closing_amount) }}</strong></div>
                            </div>
                        </li>
                    </ul>
                    <div v-else class="rounded-xl border border-dashed border-slate-200 py-6 text-center text-sm text-slate-500">Chưa có ca làm việc nào.</div>
                </section>
            </template>

            <!-- PHÂN QUYỀN -->
            <template v-else-if="activeTab === 'permissions'">
                <div v-if="data.permission_groups.length" class="grid gap-2.5 sm:grid-cols-2">
                    <div v-for="group in data.permission_groups" :key="group.group" class="rounded-xl border border-slate-200 bg-white p-3">
                        <div class="text-sm font-bold text-slate-800">{{ groupLabels[group.group] || group.group }}</div>
                        <div class="mt-1.5 flex flex-wrap gap-1">
                            <span v-for="action in group.actions" :key="action" class="rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600">{{ actionLabels[action] || action }}</span>
                        </div>
                    </div>
                </div>
                <div v-else class="rounded-xl border border-dashed border-slate-200 py-8 text-center text-sm text-slate-500">Tài khoản này chưa được cấp quyền nào.</div>
            </template>
        </template>
    </DetailShell>
</template>
