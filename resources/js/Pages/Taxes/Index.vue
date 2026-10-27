<script setup>
import { computed, ref, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { AlertCircle, ReceiptText, ShoppingCart, Wallet } from 'lucide-vue-next'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageHeader from '@/Components/UI/PageHeader.vue'
import { formatMoney, formatDate } from '@/utils/format'

defineOptions({ layout: AdminLayout })
const props = defineProps({ filters: Object, range: Object, summary: Object, sales: Array, imports: Array, taxExpenses: Array })
const year = ref(props.filters.year)
const period = ref(props.filters.period)
const years = Array.from({ length: 8 }, (_, index) => new Date().getFullYear() - index)
const quarterLabel = computed(() => `Quý ${period.value}/${year.value}`)
watch([year, period], ([nextYear, nextPeriod]) => {
    router.get(route('tax-declaration.index'), { year: nextYear, period: nextPeriod }, { preserveState: true, preserveScroll: true, replace: true })
})
</script>

<template>
    <Head title="Khai thuế" />
    <div class="space-y-4">
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

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><div class="flex items-center gap-2 text-sm font-semibold text-slate-500"><ReceiptText :size="17" /> Thuế đầu ra đã ghi nhận</div><div class="mt-2 text-2xl font-black text-blue-700">{{ formatMoney(summary.output_vat) }}</div><div class="mt-1 text-xs text-slate-500">{{ summary.sales_count }} hóa đơn bán còn hiệu lực</div></div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><div class="flex items-center gap-2 text-sm font-semibold text-slate-500"><ShoppingCart :size="17" /> Giá trị hàng nhập có VAT</div><div class="mt-2 text-2xl font-black text-emerald-700">{{ formatMoney(summary.vat_marked_purchase_value) }}</div><div class="mt-1 text-xs text-slate-500">{{ summary.purchase_count }} phiếu nhập hoàn tất · giá trị hàng, chưa phải tiền VAT</div></div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><div class="flex items-center gap-2 text-sm font-semibold text-slate-500"><Wallet :size="17" /> Chi phí thuế, phí đã ghi</div><div class="mt-2 text-2xl font-black text-violet-700">{{ formatMoney(summary.tax_expenses) }}</div><div class="mt-1 text-xs text-slate-500">Khoản chi phân loại “Thuế, phí” trong kỳ</div></div>
        </div>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-4 py-3"><h2 class="font-bold text-slate-900">Hóa đơn bán trong {{ quarterLabel }}</h2><p class="text-xs text-slate-500">Hóa đơn đã hủy được loại khỏi tổng hợp.</p></div>
            <div class="overflow-x-auto"><table class="w-full min-w-[680px] text-sm"><thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Ngày</th><th class="px-4 py-3">Mã hóa đơn</th><th class="px-4 py-3 text-right">Tiền hàng</th><th class="px-4 py-3 text-right">Giảm giá</th><th class="px-4 py-3 text-right">Thuế đầu ra</th><th class="px-4 py-3 text-right">Tổng thanh toán</th></tr></thead><tbody class="divide-y divide-slate-100"><tr v-for="sale in sales" :key="sale.id"><td class="whitespace-nowrap px-4 py-3">{{ formatDate(sale.created_at) }}</td><td class="px-4 py-3 font-semibold">{{ sale.code }}</td><td class="px-4 py-3 text-right">{{ formatMoney(sale.subtotal) }}</td><td class="px-4 py-3 text-right">{{ formatMoney(sale.discount) }}</td><td class="px-4 py-3 text-right font-semibold text-blue-700">{{ formatMoney(sale.tax) }}</td><td class="px-4 py-3 text-right">{{ formatMoney(sale.grand_total) }}</td></tr><tr v-if="!sales.length"><td colspan="6" class="px-4 py-8 text-center text-slate-500">Không có hóa đơn bán trong kỳ này.</td></tr></tbody></table></div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-4 py-3"><h2 class="font-bold text-slate-900">Phiếu nhập có đánh dấu VAT</h2><p class="text-xs text-slate-500">Giá trị được tính theo số lượng × giá nhập của các dòng có VAT.</p></div>
            <div class="overflow-x-auto"><table class="w-full min-w-[540px] text-sm"><thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Ngày nhập</th><th class="px-4 py-3">Mã phiếu</th><th class="px-4 py-3">Nhà cung cấp</th><th class="px-4 py-3 text-right">Giá trị dòng có VAT</th></tr></thead><tbody class="divide-y divide-slate-100"><tr v-for="item in imports" :key="item.id"><td class="whitespace-nowrap px-4 py-3">{{ formatDate(item.import_date) }}</td><td class="px-4 py-3 font-semibold">{{ item.code }}</td><td class="px-4 py-3">{{ item.supplier?.name || '—' }}</td><td class="px-4 py-3 text-right font-semibold text-emerald-700">{{ formatMoney(item.vat_marked_purchase_value) }}</td></tr><tr v-if="!imports.length"><td colspan="4" class="px-4 py-8 text-center text-slate-500">Không có phiếu nhập có VAT trong kỳ này.</td></tr></tbody></table></div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-4 py-3"><h2 class="font-bold text-slate-900">Chi phí thuế, phí đã ghi</h2></div>
            <div class="overflow-x-auto"><table class="w-full min-w-[480px] text-sm"><thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Ngày chi</th><th class="px-4 py-3">Nội dung</th><th class="px-4 py-3">Nơi nhận</th><th class="px-4 py-3 text-right">Số tiền</th></tr></thead><tbody class="divide-y divide-slate-100"><tr v-for="item in taxExpenses" :key="item.id"><td class="whitespace-nowrap px-4 py-3">{{ formatDate(item.expense_date) }}</td><td class="px-4 py-3">{{ item.description }}</td><td class="px-4 py-3">{{ item.recipient || '—' }}</td><td class="px-4 py-3 text-right font-semibold">{{ formatMoney(item.amount) }}</td></tr><tr v-if="!taxExpenses.length"><td colspan="4" class="px-4 py-8 text-center text-slate-500">Không có khoản chi thuế, phí trong kỳ này.</td></tr></tbody></table></div>
        </section>
    </div>
</template>
