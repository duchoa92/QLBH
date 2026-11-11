<script setup>
import { computed, ref } from 'vue'
import { Building2, Check, Copy, FileText, Landmark, Mail, MapPin, Package, Phone, StickyNote, Truck, UserRound, Wallet } from 'lucide-vue-next'
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
import StockImportDetailModal from '@/Pages/StockImports/StockImportDetailModal.vue'

const props = defineProps({
    recordId: { type: [Number, String], required: true },
    keyword: { type: String, default: '' },
    initialTab: { type: String, default: 'overview' },
    canPay: { type: Boolean, default: false },
    payHandler: { type: Function, default: null },
    modalId: { type: [Number, String], default: null },
})
const emit = defineEmits(['close'])

const { data, loading, error } = useRecordDetail('supplier', props.recordId)
const tab = ref(props.initialTab)
const copied = ref('')
const viewImportId = ref(null) // phiếu nhập đang xem ngay trong modal, không chuyển trang

const tabs = computed(() => {
    if (!data.value) return []
    const list = [{ key: 'overview', label: 'Tổng quan' }]
    if (data.value.imports) list.push({ key: 'imports', label: 'Phiếu nhập', count: data.value.imports.length })
    if (data.value.debt) list.push({ key: 'debt', label: 'Công nợ', count: data.value.debt.orders.length, tone: 'danger' })
    return list
})
const activeTab = computed(() => tabs.value.some(item => item.key === tab.value) ? tab.value : 'overview')
const owing = computed(() => (data.value?.stats.debt_balance || 0) > 0)

const copyNumber = async (number) => {
    try {
        await navigator.clipboard.writeText(number)
        copied.value = number
        setTimeout(() => { if (copied.value === number) copied.value = '' }, 1500)
    } catch { /* trình duyệt chặn clipboard: bỏ qua */ }
}

const pay = (order) => {
    emit('close')
    props.payHandler?.(order)
}
</script>

<template>
    <DetailShell size="xl" :loading="loading" :error="error" @close="emit('close')">
        <template #header>
            <div class="flex items-center gap-4">
                <DetailAvatar :name="data?.name || ''" :icon="Truck" />
                <div class="min-w-0">
                    <h2 class="truncate text-lg font-black text-slate-900"><SearchHighlight :text="data?.name || 'Nhà cung cấp'" :keyword="keyword" /></h2>
                    <div class="mt-1 flex flex-wrap items-center gap-1.5 text-xs">
                        <span v-if="data?.code" class="rounded-md bg-slate-100 px-2 py-0.5 font-bold text-slate-600"><SearchHighlight :text="data.code" :keyword="keyword" /></span>
                        <span v-if="data?.tax_code" class="rounded-md bg-blue-50 px-2 py-0.5 font-semibold text-blue-700">MST {{ data.tax_code }}</span>
                        <span v-if="data && !data.active" class="rounded-md bg-slate-200 px-2 py-0.5 font-bold text-slate-600">Ngừng hợp tác</span>
                        <span v-else-if="owing" class="rounded-md bg-amber-100 px-2 py-0.5 font-bold text-amber-700">Đang còn nợ</span>
                    </div>
                </div>
            </div>
        </template>

        <template v-if="data" #stats>
            <StatCard label="Công nợ phải trả" :value="formatMoney(data.stats.debt_balance)" :tone="owing ? 'danger' : 'success'" :icon="Wallet" :hint="owing ? 'Cần thanh toán' : 'Không còn nợ'" />
            <StatCard v-if="data.stats.total_import_value !== null" label="Tổng giá trị nhập" :value="formatMoney(data.stats.total_import_value)" tone="success" :icon="Package" />
            <StatCard v-if="data.stats.import_count !== null" label="Số phiếu nhập" :value="data.stats.import_count" tone="info" :icon="FileText" :hint="data.stats.last_import_at ? 'Gần nhất ' + data.stats.last_import_at : 'Chưa nhập lần nào'" />
        </template>

        <template v-if="data" #tabs><DetailTabs v-model="tab" :tabs="tabs" /></template>

        <template v-if="data">
            <!-- TỔNG QUAN -->
            <template v-if="activeTab === 'overview'">
                <div class="grid gap-2.5 sm:grid-cols-2">
                    <InfoItem :icon="UserRound" label="Người liên hệ" :value="data.contact_person" />
                    <InfoItem :icon="Phone" label="Điện thoại" :value="data.phone" :href="data.phone ? `tel:${data.phone}` : ''" />
                    <InfoItem :icon="Mail" label="Email" :value="data.email" :href="data.email ? `mailto:${data.email}` : ''" />
                    <InfoItem :icon="Building2" label="Mã số thuế" :value="data.tax_code" />
                    <InfoItem class="sm:col-span-2" :icon="MapPin" label="Địa chỉ" :value="data.address" />
                </div>

                <section class="space-y-2">
                    <h3 class="text-xs font-bold uppercase tracking-wide text-slate-500">Tài khoản ngân hàng</h3>
                    <div v-if="data.bank_accounts.length" class="grid gap-2.5 sm:grid-cols-2">
                        <div v-for="account in data.bank_accounts" :key="account.number" class="flex items-start gap-3 rounded-xl border border-slate-200 bg-white p-3">
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-emerald-50 text-emerald-600"><Landmark :size="18" /></span>
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-sm font-bold text-slate-800">{{ account.bank || 'Ngân hàng' }}</div>
                                <div class="font-mono text-sm text-slate-700">{{ account.number }}</div>
                                <div v-if="account.holder" class="truncate text-xs text-slate-500">{{ account.holder }}</div>
                            </div>
                            <button type="button" class="grid h-8 w-8 shrink-0 place-items-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" title="Sao chép số tài khoản" @click="copyNumber(account.number)">
                                <Check v-if="copied === account.number" :size="16" class="text-emerald-600" /><Copy v-else :size="16" />
                            </button>
                        </div>
                    </div>
                    <div v-else class="rounded-xl border border-dashed border-slate-200 py-5 text-center text-sm text-slate-500">Chưa lưu tài khoản ngân hàng.</div>
                </section>

                <div v-if="data.note" class="flex gap-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                    <StickyNote :size="18" class="mt-0.5 shrink-0" /><p class="whitespace-pre-line break-words">{{ data.note }}</p>
                </div>
                <p class="text-xs text-slate-400">Tạo ngày {{ data.created_at || '—' }}</p>
            </template>

            <!-- PHIẾU NHẬP -->
            <TransactionList v-else-if="activeTab === 'imports'" kind="import" :rows="data.imports" empty-text="Chưa có phiếu nhập nào từ nhà cung cấp này." @view="viewImportId = $event.id" />

            <!-- CÔNG NỢ -->
            <DebtLedger
                v-else-if="activeTab === 'debt'"
                entity="supplier"
                :balance="data.stats.debt_balance"
                :orders="data.debt.orders"
                :history="data.debt.history"
                :can-pay="canPay"
                @pay="pay"
                @view="viewImportId = $event.id"
            />
        </template>
    </DetailShell>

    <StockImportDetailModal v-if="viewImportId" :import-id="viewImportId" @close="viewImportId = null" />
</template>
