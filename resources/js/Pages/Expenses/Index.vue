<script setup>
import { computed, ref, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { Plus, Search, Receipt, Pencil, Trash2, X, BellRing, CalendarClock } from 'lucide-vue-next'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageHeader from '@/Components/UI/PageHeader.vue'
import BaseModal from '@/Components/UI/BaseModal.vue'
import ActionButton from '@/Components/UI/ActionButton.vue'
import FormattedNumberInput from '@/Components/UI/FormattedNumberInput.vue'
import { formatMoney } from '@/utils/format'

defineOptions({ layout: AdminLayout })
const props = defineProps({ expenses: Object, filters: Object, summary: Object, categories: Array, canManage: Boolean, recurringExpenses: { type: Array, default: () => [] } })
const categoryLabels = { utilities: 'Điện, nước', internet: 'Internet', rent: 'Thuê nhà', tax: 'Thuế, phí', salary: 'Lương', supplies: 'Văn phòng phẩm', other: 'Khác' }
const paymentLabels = { cash: 'Tiền mặt', bank: 'Chuyển khoản', card: 'Thẻ' }
const month = ref(props.filters.month)
const search = ref(props.filters.search || '')
const category = ref(props.filters.category || '')
const modal = ref(false)
const editing = ref(null)
const scheduleModal = ref(false)
const editingSchedule = ref(null)
const form = useForm({ category: 'utilities', description: '', amount: '', expense_date: new Date().toISOString().slice(0, 10), payment_method: 'cash', recipient: '', note: '' })
const scheduleForm = useForm({ category: 'utilities', description: '', amount: '', due_day: 1, remind_days: 7, payment_method: 'cash', recipient: '', note: '', is_active: true })
let timer
watch([month, search, category], () => {
    clearTimeout(timer)
    timer = setTimeout(() => router.get(route('expenses.index'), { month: month.value, search: search.value || undefined, category: category.value || undefined }, { preserveState: true, preserveScroll: true, replace: true }), 250)
})
const openCreate = () => { editing.value = null; form.reset(); form.category = 'utilities'; form.expense_date = new Date().toISOString().slice(0, 10); form.payment_method = 'cash'; modal.value = true }
const openEdit = row => { editing.value = row; form.category = row.category; form.description = row.description; form.amount = row.amount; form.expense_date = row.expense_date; form.payment_method = row.payment_method; form.recipient = row.recipient || ''; form.note = row.note || ''; modal.value = true }
const submit = () => form[editing.value ? 'put' : 'post'](editing.value ? route('expenses.update', editing.value.id) : route('expenses.store'), { preserveScroll: true, onSuccess: () => { modal.value = false } })
const remove = row => { if (confirm(`Xóa khoản chi “${row.description}”?`)) router.delete(route('expenses.destroy', row.id), { preserveScroll: true }) }
const openSchedule = row => { editingSchedule.value = row || null; scheduleForm.reset(); scheduleForm.category = row?.category || 'utilities'; scheduleForm.description = row?.description || ''; scheduleForm.amount = row?.amount || ''; scheduleForm.due_day = row?.due_day || 1; scheduleForm.remind_days = row?.remind_days ?? 7; scheduleForm.payment_method = row?.payment_method || 'cash'; scheduleForm.recipient = row?.recipient || ''; scheduleForm.note = row?.note || ''; scheduleForm.is_active = row?.is_active ?? true; scheduleModal.value = true }
const saveSchedule = () => scheduleForm[editingSchedule.value ? 'put' : 'post'](editingSchedule.value ? route('expenses.recurring.update', editingSchedule.value.id) : route('expenses.recurring.store'), { preserveScroll: true, onSuccess: () => { scheduleModal.value = false } })
const deleteSchedule = row => { if (confirm(`Xóa lịch nhắc “${row.description}”?`)) router.delete(route('expenses.recurring.destroy', row.id), { preserveScroll: true }) }
const markPaid = row => {
    if (!confirm(`Ghi nhận đã thanh toán ${row.description} trong tháng ${props.filters.month}?`)) return
    const entered = window.prompt('Số tiền thực tế đã đóng (VNĐ):', String(Number(row.amount)))
    if (entered === null) return
    const amount = Number(entered.replace(/[,.\s]/g, ''))
    if (!Number.isFinite(amount) || amount <= 0) return alert('Vui lòng nhập số tiền hợp lệ.')
    router.post(route('expenses.recurring.paid', row.id), { period: props.filters.month, paid_date: new Date().toISOString().slice(0, 10), amount, payment_method: row.payment_method }, { preserveScroll: true })
}
const pageTotal = computed(() => props.expenses?.data?.length || 0)
</script>

<template>
    <div class="space-y-4 p-2 sm:p-3">
        <PageHeader title="Chi phí cửa hàng" description="Theo dõi tiền điện nước, internet, thuê nhà, thuế và các khoản chi khác.">
            <template #actions><ActionButton v-if="canManage" @click="openCreate"><Plus :size="16" class="mr-1" /> Thêm chi phí</ActionButton></template>
        </PageHeader>
        <div class="grid gap-3 sm:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs font-semibold text-slate-500">Tổng chi tháng {{ month }}</p><p class="mt-1 text-2xl font-black text-rose-700">{{ formatMoney(summary.total) }}</p></div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs font-semibold text-slate-500">Số khoản chi trong tháng</p><p class="mt-1 text-2xl font-black text-slate-900">{{ summary.count }}</p></div>
        </div>
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 p-3">
                <input v-model="month" type="month" class="rounded-xl border-slate-200 text-sm" />
                <select v-model="category" class="rounded-xl border-slate-200 text-sm"><option value="">Tất cả loại chi</option><option v-for="item in categories" :key="item" :value="item">{{ categoryLabels[item] || item }}</option></select>
                <div class="relative min-w-48 flex-1"><Search :size="15" class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" /><input v-model="search" placeholder="Tìm nội dung hoặc người nhận" class="w-full rounded-xl border-slate-200 pl-9 text-sm" /></div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="p-3">Ngày</th><th class="p-3">Loại</th><th class="p-3">Nội dung</th><th class="p-3">Người nhận</th><th class="p-3">Thanh toán</th><th class="p-3 text-right">Số tiền</th><th v-if="canManage" class="p-3 text-right">Thao tác</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="row in expenses.data" :key="row.id" class="hover:bg-slate-50"><td class="p-3">{{ row.expense_date }}</td><td class="p-3"><span class="rounded-full bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-800">{{ categoryLabels[row.category] || row.category }}</span></td><td class="p-3"><div class="font-semibold text-slate-800">{{ row.description }}</div><div v-if="row.note" class="mt-0.5 text-xs text-slate-500">{{ row.note }}</div></td><td class="p-3 text-slate-600">{{ row.recipient || '—' }}</td><td class="p-3 text-slate-600">{{ paymentLabels[row.payment_method] || row.payment_method }}</td><td class="p-3 text-right font-bold text-rose-700">{{ formatMoney(row.amount) }}</td><td v-if="canManage" class="p-3 text-right"><button class="mr-2 rounded-lg p-2 text-blue-700 hover:bg-blue-50" aria-label="Sửa" @click="openEdit(row)"><Pencil :size="15" /></button><button class="rounded-lg p-2 text-rose-700 hover:bg-rose-50" aria-label="Xóa" @click="remove(row)"><Trash2 :size="15" /></button></td></tr>
                        <tr v-if="!pageTotal"><td :colspan="canManage ? 7 : 6" class="p-10 text-center text-slate-500"><Receipt :size="28" class="mx-auto mb-2 text-slate-300" />Chưa có khoản chi phù hợp.</td></tr>
                    </tbody>
                </table>
            </div>
            <div v-if="expenses.links?.length > 3" class="flex flex-wrap justify-end gap-1 border-t border-slate-100 p-3"><button v-for="link in expenses.links" :key="link.label" :disabled="!link.url" class="rounded-lg border px-3 py-1.5 text-xs disabled:opacity-40" :class="link.active ? 'border-emerald-600 bg-emerald-600 text-white' : 'border-slate-200'" @click="router.get(link.url, {}, { preserveScroll: true })"><span v-html="link.label" /></button></div>
        </section>
        <section class="overflow-hidden rounded-2xl border border-blue-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-blue-100 bg-blue-50/70 p-4">
                <div><h2 class="flex items-center gap-2 font-bold text-slate-900"><BellRing :size="18" class="text-blue-700" />Lịch chi phí cố định</h2><p class="mt-1 text-xs text-slate-600">Nhắc trước hạn và hiện nhắc việc trên trang tổng quan. Tháng đang xem: {{ month }}.</p></div>
                <ActionButton v-if="canManage" @click="openSchedule()"><Plus :size="15" />Thêm lịch định kỳ</ActionButton>
            </div>
            <div v-if="recurringExpenses.length" class="divide-y divide-slate-100">
                <div v-for="row in recurringExpenses" :key="row.id" class="flex flex-wrap items-center justify-between gap-3 p-4" :class="!row.is_active ? 'opacity-60' : ''">
                    <div class="min-w-48 flex-1"><div class="flex flex-wrap items-center gap-2"><strong class="text-sm text-slate-900">{{ row.description }}</strong><span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] text-slate-600">{{ categoryLabels[row.category] || row.category }}</span><span v-if="!row.is_active" class="text-xs text-slate-500">Đã tạm dừng</span></div><div class="mt-1 flex flex-wrap gap-x-3 text-xs text-slate-500"><span class="inline-flex items-center gap-1"><CalendarClock :size="13" />Ngày {{ row.due_day }} hàng tháng · nhắc trước {{ row.remind_days }} ngày</span><span>{{ formatMoney(row.amount) }}</span><span v-if="row.recipient">{{ row.recipient }}</span></div></div>
                    <div v-if="canManage" class="flex items-center gap-2"><span v-if="row.payments?.length" class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700">Đã thanh toán tháng {{ month }}</span><ActionButton v-else-if="row.is_active" variant="info" @click="markPaid(row)">Đã thanh toán</ActionButton><button class="rounded-lg p-2 text-blue-700 hover:bg-blue-50" aria-label="Sửa lịch" @click="openSchedule(row)"><Pencil :size="15" /></button><button class="rounded-lg p-2 text-rose-700 hover:bg-rose-50" aria-label="Xóa lịch" @click="deleteSchedule(row)"><Trash2 :size="15" /></button></div>
                </div>
            </div>
            <div v-else class="p-8 text-center text-sm text-slate-500">Chưa có lịch chi phí định kỳ.</div>
        </section>
        <BaseModal v-if="modal" :title="editing ? 'Cập nhật khoản chi' : 'Thêm khoản chi'" size="md" @close="modal = false">
            <form class="space-y-3" @submit.prevent="submit">
                <div class="grid gap-3 sm:grid-cols-2"><label class="text-xs font-semibold text-slate-600">Loại chi<select v-model="form.category" class="mt-1 w-full rounded-xl border-slate-200 text-sm"><option v-for="item in categories" :key="item" :value="item">{{ categoryLabels[item] || item }}</option></select><small class="text-rose-600">{{ form.errors.category }}</small></label><label class="text-xs font-semibold text-slate-600">Ngày chi<input v-model="form.expense_date" type="date" class="mt-1 w-full rounded-xl border-slate-200 text-sm" /><small class="text-rose-600">{{ form.errors.expense_date }}</small></label></div>
                <label class="block text-xs font-semibold text-slate-600">Nội dung khoản chi<input v-model="form.description" maxlength="255" placeholder="Ví dụ: Tiền điện tháng 10" class="mt-1 w-full rounded-xl border-slate-200 text-sm" /><small class="text-rose-600">{{ form.errors.description }}</small></label>
                <div class="grid gap-3 sm:grid-cols-2"><label class="text-xs font-semibold text-slate-600">Số tiền (VNĐ)<FormattedNumberInput v-model="form.amount" class="mt-1 w-full rounded-xl border-slate-200 text-sm" /><small class="text-rose-600">{{ form.errors.amount }}</small></label><label class="text-xs font-semibold text-slate-600">Hình thức<select v-model="form.payment_method" class="mt-1 w-full rounded-xl border-slate-200 text-sm"><option value="cash">Tiền mặt</option><option value="bank">Chuyển khoản</option><option value="card">Thẻ</option></select></label></div>
                <label class="block text-xs font-semibold text-slate-600">Người nhận / đơn vị<input v-model="form.recipient" maxlength="150" placeholder="Ví dụ: Điện lực, chủ nhà" class="mt-1 w-full rounded-xl border-slate-200 text-sm" /></label>
                <label class="block text-xs font-semibold text-slate-600">Ghi chú<textarea v-model="form.note" rows="2" class="mt-1 w-full rounded-xl border-slate-200 text-sm" /></label>
                <div class="flex justify-end gap-2 pt-2"><ActionButton type="button" variant="secondary" @click="modal = false"><X :size="15" class="mr-1" />Đóng</ActionButton><ActionButton type="submit" :disabled="form.processing">{{ editing ? 'Lưu thay đổi' : 'Ghi nhận chi phí' }}</ActionButton></div>
            </form>
        </BaseModal>
        <BaseModal v-if="scheduleModal" :title="editingSchedule ? 'Cập nhật lịch chi phí' : 'Tạo lịch chi phí định kỳ'" size="md" @close="scheduleModal = false">
            <form class="space-y-3" @submit.prevent="saveSchedule">
                <div class="grid gap-3 sm:grid-cols-2"><label class="text-xs font-semibold text-slate-600">Loại chi<select v-model="scheduleForm.category" class="mt-1 w-full rounded-xl border-slate-200 text-sm"><option v-for="item in categories" :key="item" :value="item">{{ categoryLabels[item] || item }}</option></select></label><label class="text-xs font-semibold text-slate-600">Tên khoản chi<input v-model="scheduleForm.description" placeholder="Ví dụ: Tiền thuê nhà" class="mt-1 w-full rounded-xl border-slate-200 text-sm" /><small class="text-rose-600">{{ scheduleForm.errors.description }}</small></label></div>
                <div class="grid gap-3 sm:grid-cols-3"><label class="text-xs font-semibold text-slate-600">Số tiền dự kiến<FormattedNumberInput v-model="scheduleForm.amount" class="mt-1 w-full rounded-xl border-slate-200 text-sm" /><small class="text-rose-600">{{ scheduleForm.errors.amount }}</small></label><label class="text-xs font-semibold text-slate-600">Ngày đến hạn<input v-model.number="scheduleForm.due_day" type="number" min="1" max="31" class="mt-1 w-full rounded-xl border-slate-200 text-sm" /><small class="text-rose-600">{{ scheduleForm.errors.due_day }}</small></label><label class="text-xs font-semibold text-slate-600">Nhắc trước (ngày)<input v-model.number="scheduleForm.remind_days" type="number" min="0" max="30" class="mt-1 w-full rounded-xl border-slate-200 text-sm" /><small class="text-rose-600">{{ scheduleForm.errors.remind_days }}</small></label></div>
                <div class="grid gap-3 sm:grid-cols-2"><label class="text-xs font-semibold text-slate-600">Người nhận / đơn vị<input v-model="scheduleForm.recipient" placeholder="Điện lực, chủ nhà..." class="mt-1 w-full rounded-xl border-slate-200 text-sm" /></label><label class="text-xs font-semibold text-slate-600">Hình thức thanh toán<select v-model="scheduleForm.payment_method" class="mt-1 w-full rounded-xl border-slate-200 text-sm"><option value="cash">Tiền mặt</option><option value="bank">Chuyển khoản</option><option value="card">Thẻ</option></select></label></div>
                <label class="flex items-center gap-2 text-sm text-slate-700"><input v-model="scheduleForm.is_active" type="checkbox" class="rounded border-slate-300" />Bật nhắc lịch này</label>
                <label class="block text-xs font-semibold text-slate-600">Ghi chú<textarea v-model="scheduleForm.note" rows="2" class="mt-1 w-full rounded-xl border-slate-200 text-sm" /></label>
                <div class="flex justify-end gap-2 pt-2"><ActionButton type="button" variant="secondary" @click="scheduleModal = false">Đóng</ActionButton><ActionButton type="submit" :disabled="scheduleForm.processing">Lưu lịch nhắc</ActionButton></div>
            </form>
        </BaseModal>
    </div>
</template>
