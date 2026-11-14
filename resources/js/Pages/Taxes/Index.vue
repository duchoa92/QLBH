<script setup>
import { computed, ref, watch } from 'vue'
import { useUrlView } from '@/Composables/useUrlState'
import { Head } from '@inertiajs/vue3'
import { AlertCircle, Banknote, FileSpreadsheet, FilterX, Landmark, ListFilter, ReceiptText, ShoppingCart, Wallet } from 'lucide-vue-next'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageHeader from '@/Components/UI/PageHeader.vue'
import ListToolbar from '@/Components/UI/ListToolbar.vue'
import ListFilterSelect from '@/Components/UI/ListFilterSelect.vue'
import { useLiveList } from '@/Composables/useLiveList'
import { formatMoney, formatDate } from '@/utils/format'

defineOptions({ layout: AdminLayout })
const props = defineProps({
    filters: Object, range: Object, summary: Object, sales: Array, items: { type: Array, default: () => [] }, imports: Array, taxExpenses: Array,
    bankOptions: { type: Array, default: () => [] },
    bankBreakdown: { type: Array, default: () => [] },
})

const year = ref(props.filters.year)
const period = ref(props.filters.period)
const search = ref(props.filters.search || '')
const vat = ref(props.filters.vat || '')
const receipt = ref(props.filters.receipt || '')
const bankAccount = ref(props.filters.bank_account || '')
const years = Array.from({ length: 8 }, (_, index) => new Date().getFullYear() - index)

const vatOptions = [
    { value: '', label: 'Tất cả (VAT & không VAT)' },
    { value: 'with_vat', label: 'Hóa đơn có VAT' },
    { value: 'without_vat', label: 'Hóa đơn không VAT' },
]
const receiptOptions = [
    { value: '', label: 'Tất cả hình thức' },
    { value: 'cash', label: 'Có nhận tiền mặt' },
    { value: 'bank', label: 'Có nhận chuyển khoản' },
    { value: 'mixed', label: 'Thanh toán kết hợp' },
    { value: 'card', label: 'Quẹt thẻ' },
    { value: 'debt', label: 'Còn nợ chưa thu đủ' },
]
const methodLabels = { cash: 'Tiền mặt', bank: 'Chuyển khoản', mixed: 'Kết hợp', card: 'Thẻ' }

// Lọc bằng AJAX, không tải lại trang (cùng cách với các trang danh sách khác).
const list = useLiveList({
    props,
    dataKey: 'sales',
    extraKeys: ['summary', 'items', 'imports', 'taxExpenses', 'range', 'bankOptions', 'bankBreakdown'],
    params: () => ({
        year: year.value, period: period.value, search: search.value.trim(),
        vat: vat.value, receipt: receipt.value, bank_account: bankAccount.value,
    }),
})
const { data: salesData, extras, loading } = list
const summary = extras.summary
const range = extras.range
const itemsData = extras.items
const importsData = extras.imports
const taxExpensesData = extras.taxExpenses
const bankBreakdownData = extras.bankBreakdown
const bankOptionsData = extras.bankOptions

watch(search, () => list.schedule())
watch([year, period, vat, receipt, bankAccount], () => list.request())

const filterOpen = ref(false)
const activeTab = ref('sales')
useUrlView({
    key: 'tab',
    current: () => (activeTab.value === 'sales' ? null : activeTab.value),
    restore: value => { activeTab.value = value === 'items' ? 'items' : 'sales' },
})
const activeFilterCount = computed(() => [vat.value, receipt.value, bankAccount.value].filter(Boolean).length)
const hasFilter = computed(() => Boolean(search.value || activeFilterCount.value))
const clearFilters = () => { search.value = ''; vat.value = ''; receipt.value = ''; bankAccount.value = '' }
const clearSelectFilters = () => { vat.value = ''; receipt.value = ''; bankAccount.value = '' }

// Xuất Excel đúng theo kỳ và bộ lọc đang chọn (tải thẳng file, không qua Inertia).
const exportUrl = computed(() => {
    const query = new URLSearchParams()
    const values = { year: year.value, period: period.value, search: search.value.trim(), vat: vat.value, receipt: receipt.value, bank_account: bankAccount.value, export: 'xlsx' }
    Object.entries(values).forEach(([key, value]) => { if (value !== '' && value !== null && value !== undefined) query.set(key, String(value)) })
    return `${route('tax-declaration.index')}?${query.toString()}`
})
const itemTotals = computed(() => (itemsData.value || []).reduce((total, row) => ({
    quantity: total.quantity + Number(row.quantity || 0),
    subtotal: total.subtotal + Number(row.subtotal || 0),
    vat: total.vat + Number(row.vat_amount || 0),
}), { quantity: 0, subtotal: 0, vat: 0 }))
const quarterLabel = computed(() => `Quý ${period.value}/${year.value}`)
// Danh sách tài khoản trong ô lọc luôn lấy theo kỳ đang xem (có thể đổi khi đổi quý).
const bankAccountSelectOptions = computed(() => [
    { value: '', label: 'Tất cả tài khoản' },
    ...(bankOptionsData.value || []),
    { value: '__none__', label: 'Không ghi tài khoản (tiền mặt/thẻ/nợ)' },
])
</script>

<template>
    <Head title="Khai thuế" />
    <div class="space-y-3">
        <PageHeader title="Khai thuế">
            <template #actions>
                <div class="flex flex-wrap items-center gap-2">
                    <select v-model.number="period" aria-label="Kỳ kê khai" class="rounded-xl border-slate-200 text-sm font-medium">
                        <option :value="1">Quý 1</option><option :value="2">Quý 2</option><option :value="3">Quý 3</option><option :value="4">Quý 4</option>
                    </select>
                    <select v-model.number="year" aria-label="Năm kê khai" class="rounded-xl border-slate-200 text-sm font-medium">
                        <option v-for="item in years" :key="item" :value="item">Năm {{ item }}</option>
                    </select>
                </div>
            </template>
        </PageHeader>

        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
            <div class="flex gap-2"><AlertCircle :size="18" class="mt-0.5 shrink-0" /><div><strong>Bảng tổng hợp tham khảo · {{ quarterLabel }} ({{ formatDate(range.from) }} – {{ formatDate(range.to) }})</strong><p class="mt-1 text-amber-900">Thuế đầu ra lấy từ trường thuế đã lưu trên hóa đơn bán. Phiếu nhập mới chỉ ghi nhận cờ “Có VAT” và giá nhập, chưa lưu số thuế VAT trên hóa đơn đầu vào; vì vậy số tiền mua hàng có VAT dưới đây không phải thuế GTGT đầu vào được khấu trừ và hệ thống chưa tính số thuế phải nộp. Vui lòng đối chiếu hóa đơn chứng từ trước khi nộp tờ khai.</p></div></div>
        </div>

        <ListToolbar v-model="search" placeholder="Tìm mã hóa đơn, khách hàng, sản phẩm, SKU, IMEI..." :loading="loading">
            <template #filters>
                <button type="button" class="relative inline-flex h-[42px] items-center justify-center gap-2 rounded-xl border px-3.5 text-xs font-semibold transition" :class="filterOpen || activeFilterCount ? 'border-blue-300 bg-blue-50 text-blue-700' : 'border-slate-200 bg-white text-slate-700 hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700'" :aria-expanded="filterOpen" @click="filterOpen = !filterOpen">
                    <ListFilter :size="16" /> Bộ lọc
                    <span v-if="activeFilterCount" class="grid h-5 min-w-[20px] place-items-center rounded-full bg-blue-600 px-1 text-[11px] font-bold text-white">{{ activeFilterCount }}</span>
                </button>
                <a :href="exportUrl" class="inline-flex h-[42px] items-center justify-center gap-2 rounded-xl bg-emerald-600 px-3.5 text-xs font-bold text-white shadow-sm transition hover:bg-emerald-700" title="Tải file Excel theo kỳ và bộ lọc đang chọn"><FileSpreadsheet :size="16" /> Xuất Excel</a>
            </template>
            <template v-if="filterOpen" #expanded>
                <div class="grid gap-2.5 sm:grid-cols-2 xl:grid-cols-4">
                    <ListFilterSelect v-model="vat" label="VAT" :options="vatOptions" width="sm:w-full" />
                    <ListFilterSelect v-model="receipt" label="Hình thức nhận tiền" :options="receiptOptions" width="sm:w-full" />
                    <ListFilterSelect v-model="bankAccount" label="Nhận tiền vào tài khoản" :options="bankAccountSelectOptions" width="sm:w-full" />
                    <button type="button" :disabled="!activeFilterCount" class="inline-flex h-[42px] items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-600 transition hover:border-rose-300 hover:bg-rose-50 hover:text-rose-600 disabled:cursor-not-allowed disabled:opacity-50" @click="clearSelectFilters"><FilterX :size="15" /> Xóa bộ lọc</button>
                </div>
            </template>
        </ListToolbar>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><div class="flex items-center gap-2 text-sm font-semibold text-slate-500"><ReceiptText :size="17" /> Thuế đầu ra đã ghi nhận</div><div class="mt-2 text-2xl font-black text-blue-700">{{ formatMoney(summary.output_vat) }}</div><div class="mt-1 text-xs text-slate-500">{{ summary.sales_count }} hóa đơn · {{ summary.items_count }} dòng sản phẩm{{ hasFilter ? ' (theo bộ lọc)' : '' }}</div></div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><div class="flex items-center gap-2 text-sm font-semibold text-slate-500"><Banknote :size="17" /> Tiền mặt đã nhận</div><div class="mt-2 text-2xl font-black text-amber-700">{{ formatMoney(summary.cash_received) }}</div><div class="mt-1 text-xs text-slate-500">Tiền khách đưa đã trừ tiền thối lại</div></div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><div class="flex items-center gap-2 text-sm font-semibold text-slate-500"><Landmark :size="17" /> Chuyển khoản / thẻ đã nhận</div><div class="mt-2 text-2xl font-black text-sky-700">{{ formatMoney(summary.bank_received) }}</div><div class="mt-1 text-xs text-slate-500">Còn nợ chưa thu: {{ formatMoney(summary.debt_total) }}</div></div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><div class="flex items-center gap-2 text-sm font-semibold text-slate-500"><ShoppingCart :size="17" /> Giá trị hàng nhập có VAT</div><div class="mt-2 text-2xl font-black text-emerald-700">{{ formatMoney(summary.vat_marked_purchase_value) }}</div><div class="mt-1 text-xs text-slate-500">{{ summary.purchase_count }} phiếu nhập hoàn tất · giá trị hàng, chưa phải tiền VAT</div></div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><div class="flex items-center gap-2 text-sm font-semibold text-slate-500"><Wallet :size="17" /> Chi phí thuế, phí đã ghi</div><div class="mt-2 text-2xl font-black text-violet-700">{{ formatMoney(summary.tax_expenses) }}</div><div class="mt-1 text-xs text-slate-500">Khoản chi phân loại “Thuế, phí” trong kỳ</div></div>
        </div>

        <section v-if="bankBreakdownData.length" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="font-bold text-slate-900">Tiền chuyển khoản theo tài khoản nhận</h2>
            <p class="text-xs text-slate-500">Bấm vào tài khoản để lọc các hóa đơn nhận tiền vào tài khoản đó.</p>
            <div class="mt-3 grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                <button v-for="item in bankBreakdownData" :key="item.value" type="button" class="rounded-xl border px-3 py-2.5 text-left transition hover:border-blue-300 hover:bg-blue-50" :class="bankAccount === item.value ? 'border-blue-400 bg-blue-50' : 'border-slate-200'" @click="bankAccount = bankAccount === item.value ? '' : item.value">
                    <div class="truncate text-xs font-semibold text-slate-600">{{ item.label }}</div>
                    <div class="mt-0.5 text-lg font-black text-sky-700">{{ formatMoney(item.amount) }}</div>
                    <div class="text-[11px] text-slate-500">{{ item.count }} hóa đơn</div>
                </button>
            </div>
        </section>

        <div class="inline-flex rounded-xl border border-slate-200 bg-white p-1 shadow-sm">
            <button type="button" class="rounded-lg px-3.5 py-2 text-xs font-bold transition" :class="activeTab === 'sales' ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100'" @click="activeTab = 'sales'">Hóa đơn bán ({{ salesData.length }})</button>
            <button type="button" class="rounded-lg px-3.5 py-2 text-xs font-bold transition" :class="activeTab === 'items' ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100'" @click="activeTab = 'items'">Chi tiết sản phẩm bán ({{ itemsData.length }})</button>
        </div>

        <section v-show="activeTab === 'sales'" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-4 py-3"><h2 class="font-bold text-slate-900">Hóa đơn bán trong {{ quarterLabel }}</h2><p class="text-xs text-slate-500">Hóa đơn đã hủy được loại khỏi tổng hợp. Bộ lọc VAT, hình thức và tài khoản nhận tiền áp dụng cho bảng này.</p></div>
            <div class="overflow-x-auto"><table class="w-full min-w-[1100px] text-sm"><thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Ngày</th><th class="px-4 py-3">Mã hóa đơn</th><th class="px-4 py-3 text-right">Tiền hàng</th><th class="px-4 py-3 text-right">Giảm giá</th><th class="px-4 py-3 text-right">Thuế đầu ra</th><th class="px-4 py-3 text-right">Tổng thanh toán</th><th class="px-4 py-3">Hình thức</th><th class="px-4 py-3">TK nhận tiền</th><th class="px-4 py-3 text-right">Tiền mặt</th><th class="px-4 py-3 text-right">CK / thẻ</th></tr></thead><tbody class="divide-y divide-slate-100"><tr v-for="sale in salesData" :key="sale.id"><td class="whitespace-nowrap px-4 py-3">{{ formatDate(sale.created_at) }}</td><td class="px-4 py-3"><div class="font-semibold">{{ sale.code }}</div><div v-if="sale.customer_name" class="text-xs text-slate-500">{{ sale.customer_name }}</div></td><td class="px-4 py-3 text-right">{{ formatMoney(sale.subtotal) }}</td><td class="px-4 py-3 text-right">{{ formatMoney(sale.discount) }}</td><td class="px-4 py-3 text-right font-semibold" :class="sale.tax > 0 ? 'text-blue-700' : 'text-slate-400'">{{ sale.tax > 0 ? formatMoney(sale.tax) : 'Không VAT' }}</td><td class="px-4 py-3 text-right">{{ formatMoney(sale.grand_total) }}<div v-if="sale.debt_amount > 0" class="text-[11px] font-semibold text-rose-600">Nợ {{ formatMoney(sale.debt_amount) }}</div></td><td class="whitespace-nowrap px-4 py-3">{{ methodLabels[sale.payment_method] || sale.payment_method || '—' }}</td><td class="px-4 py-3 text-xs">{{ sale.bank_account_label || '—' }}</td><td class="px-4 py-3 text-right">{{ sale.cash_amount > 0 ? formatMoney(sale.cash_amount) : '—' }}</td><td class="px-4 py-3 text-right">{{ sale.bank_amount > 0 ? formatMoney(sale.bank_amount) : '—' }}</td></tr><tr v-if="!salesData.length"><td colspan="10" class="px-4 py-8 text-center text-slate-500">{{ hasFilter ? 'Không có hóa đơn nào khớp bộ lọc trong kỳ này.' : 'Không có hóa đơn bán trong kỳ này.' }}</td></tr></tbody></table></div>
        </section>

        <section v-show="activeTab === 'items'" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-4 py-3"><h2 class="font-bold text-slate-900">Chi tiết sản phẩm bán trong {{ quarterLabel }}</h2><p class="text-xs text-slate-500">Từng dòng sản phẩm của các hóa đơn đang lọc. Hệ thống chỉ lưu VAT theo hóa đơn nên VAT từng dòng là số phân bổ ước tính theo tỷ lệ thành tiền của các dòng chịu VAT.</p></div>
            <div class="overflow-x-auto"><table class="w-full min-w-[1150px] text-sm"><thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Ngày</th><th class="px-4 py-3">Mã hóa đơn</th><th class="px-4 py-3">Sản phẩm</th><th class="px-4 py-3">ĐVT</th><th class="px-4 py-3 text-right">SL</th><th class="px-4 py-3 text-right">Đơn giá</th><th class="px-4 py-3 text-right">Giảm giá</th><th class="px-4 py-3 text-right">Thành tiền</th><th class="px-4 py-3">VAT</th><th class="px-4 py-3">Hình thức / TK nhận</th></tr></thead><tbody class="divide-y divide-slate-100"><tr v-for="row in itemsData" :key="row.key"><td class="whitespace-nowrap px-4 py-3">{{ formatDate(row.created_at) }}</td><td class="px-4 py-3"><div class="font-semibold">{{ row.sale_code }}</div><div v-if="row.customer_name" class="text-xs text-slate-500">{{ row.customer_name }}</div></td><td class="px-4 py-3"><div class="font-medium text-slate-900">{{ row.product_name }}</div><div class="text-xs text-slate-500"><span v-if="row.sku">SKU: {{ row.sku }}</span><span v-if="row.sku && row.imei"> · </span><span v-if="row.imei">IMEI/Serial: {{ row.imei }}</span></div></td><td class="px-4 py-3">{{ row.unit_name || '—' }}</td><td class="px-4 py-3 text-right">{{ row.quantity }}</td><td class="px-4 py-3 text-right">{{ formatMoney(row.unit_price) }}</td><td class="px-4 py-3 text-right">{{ row.discount > 0 ? formatMoney(row.discount) : '—' }}</td><td class="px-4 py-3 text-right font-semibold">{{ formatMoney(row.subtotal) }}</td><td class="px-4 py-3"><span v-if="row.vat_applied" class="inline-flex rounded-full bg-blue-50 px-2 py-0.5 text-[11px] font-bold text-blue-700">Chịu VAT</span><span v-else class="text-xs text-slate-400">Không VAT</span><div v-if="row.vat_amount > 0" class="mt-0.5 text-xs font-semibold text-blue-700">≈ {{ formatMoney(row.vat_amount) }}</div></td><td class="px-4 py-3 text-xs"><div>{{ methodLabels[row.payment_method] || row.payment_method || '—' }}</div><div v-if="row.bank_account_label" class="text-slate-500">{{ row.bank_account_label }}</div></td></tr><tr v-if="!itemsData.length"><td colspan="10" class="px-4 py-8 text-center text-slate-500">{{ hasFilter ? 'Không có sản phẩm nào khớp bộ lọc trong kỳ này.' : 'Không có sản phẩm bán trong kỳ này.' }}</td></tr></tbody><tfoot v-if="itemsData.length" class="bg-slate-50 text-sm font-bold text-slate-800"><tr><td colspan="4" class="px-4 py-3 text-right">Tổng cộng</td><td class="px-4 py-3 text-right">{{ itemTotals.quantity }}</td><td class="px-4 py-3"></td><td class="px-4 py-3"></td><td class="px-4 py-3 text-right">{{ formatMoney(itemTotals.subtotal) }}</td><td class="px-4 py-3 text-blue-700">≈ {{ formatMoney(itemTotals.vat) }}</td><td class="px-4 py-3"></td></tr></tfoot></table></div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-4 py-3"><h2 class="font-bold text-slate-900">Phiếu nhập có đánh dấu VAT</h2><p class="text-xs text-slate-500">Giá trị được tính theo số lượng × giá nhập của các dòng có VAT.</p></div>
            <div class="overflow-x-auto"><table class="w-full min-w-[540px] text-sm"><thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Ngày nhập</th><th class="px-4 py-3">Mã phiếu</th><th class="px-4 py-3">Nhà cung cấp</th><th class="px-4 py-3 text-right">Giá trị dòng có VAT</th></tr></thead><tbody class="divide-y divide-slate-100"><tr v-for="item in importsData" :key="item.id"><td class="whitespace-nowrap px-4 py-3">{{ formatDate(item.import_date) }}</td><td class="px-4 py-3 font-semibold">{{ item.code }}</td><td class="px-4 py-3">{{ item.supplier?.name || '—' }}</td><td class="px-4 py-3 text-right font-semibold text-emerald-700">{{ formatMoney(item.vat_marked_purchase_value) }}</td></tr><tr v-if="!importsData.length"><td colspan="4" class="px-4 py-8 text-center text-slate-500">Không có phiếu nhập có VAT trong kỳ này.</td></tr></tbody></table></div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-4 py-3"><h2 class="font-bold text-slate-900">Chi phí thuế, phí đã ghi</h2></div>
            <div class="overflow-x-auto"><table class="w-full min-w-[480px] text-sm"><thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Ngày chi</th><th class="px-4 py-3">Nội dung</th><th class="px-4 py-3">Nơi nhận</th><th class="px-4 py-3 text-right">Số tiền</th></tr></thead><tbody class="divide-y divide-slate-100"><tr v-for="item in taxExpensesData" :key="item.id"><td class="whitespace-nowrap px-4 py-3">{{ formatDate(item.expense_date) }}</td><td class="px-4 py-3">{{ item.description }}</td><td class="px-4 py-3">{{ item.recipient || '—' }}</td><td class="px-4 py-3 text-right font-semibold">{{ formatMoney(item.amount) }}</td></tr><tr v-if="!taxExpensesData.length"><td colspan="4" class="px-4 py-8 text-center text-slate-500">Không có khoản chi thuế, phí trong kỳ này.</td></tr></tbody></table></div>
        </section>
    </div>
</template>
