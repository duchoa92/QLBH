<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageHeader from '@/Components/UI/PageHeader.vue'
import FloatingSelect from '@/Components/UI/FloatingSelect.vue'
import { Wallet, RotateCcw } from 'lucide-vue-next'
import { formatMoney as formatConfiguredMoney } from '@/utils/format'

defineOptions({ layout: AdminLayout })
const props = defineProps({ shifts: { type: Array, default: () => [] } })
const page = usePage()
const denominations = [500000, 200000, 100000, 50000, 20000, 10000, 5000, 2000, 1000]
const money = formatConfiguredMoney
const keyOf = date => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
const today = new Date()
const cutoff = new Date(today.getFullYear(), today.getMonth(), today.getDate() - 29)
const cutoffKey = keyOf(cutoff)
const todayKey = keyOf(today)
const employeeId = ref('')
const calendarOpen = ref(false)
const calendarRoot = ref(null)
// Bấm ra ngoài vùng chọn ngày hoặc nhấn Esc thì đóng lịch.
const closeCalendarOnOutside = (event) => {
    if (calendarOpen.value && calendarRoot.value && !calendarRoot.value.contains(event.target)) calendarOpen.value = false
}
const closeCalendarOnEscape = (event) => { if (event.key === 'Escape') calendarOpen.value = false }
onMounted(() => {
    document.addEventListener('pointerdown', closeCalendarOnOutside)
    document.addEventListener('keydown', closeCalendarOnEscape)
})
onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', closeCalendarOnOutside)
    document.removeEventListener('keydown', closeCalendarOnEscape)
})
const expandedShiftId = ref(null)
const filteredShifts = computed(() => props.shifts.filter(shift => {
    if (employeeId.value && String(shift.user_id) !== String(employeeId.value)) return false
    return true
}))
const employees = computed(() => {
    const unique = new Map()
    for (const shift of props.shifts) {
        if (shift.user_id && shift.user_name) unique.set(String(shift.user_id), shift.user_name)
    }
    return [...unique.entries()].map(([id, name]) => ({ id, name })).sort((a, b) => a.name.localeCompare(b.name, 'vi'))
})
const shiftsByDate = computed(() => {
    const grouped = new Map()
    for (const shift of filteredShifts.value) {
        if (!grouped.has(shift.closed_date)) grouped.set(shift.closed_date, [])
        grouped.get(shift.closed_date).push(shift)
    }
    return grouped
})
// Số thứ tự ca trong ngày: mỗi ngày đếm lại từ Ca 1 (theo thứ tự mở ca), không phụ thuộc bộ lọc nhân viên.
const shiftNumberById = computed(() => {
    const byDate = new Map()
    for (const shift of props.shifts) {
        if (!byDate.has(shift.closed_date)) byDate.set(shift.closed_date, [])
        byDate.get(shift.closed_date).push(shift)
    }
    const numbers = new Map()
    for (const list of byDate.values()) {
        list.sort((a, b) => Number(a.id) - Number(b.id)).forEach((shift, index) => numbers.set(shift.id, index + 1))
    }
    return numbers
})
const newestDate = props.shifts[0]?.closed_date || keyOf(today)
const selectedDate = ref(newestDate)
const selectedShifts = computed(() => shiftsByDate.value.get(selectedDate.value) || [])
const dateLabel = date => new Intl.DateTimeFormat('vi-VN', { dateStyle: 'full' }).format(new Date(`${date}T00:00:00`))
const dateOptions = computed(() => [...shiftsByDate.value.entries()].sort(([a], [b]) => b.localeCompare(a)).map(([date, shifts]) => ({
    date,
    shifts,
    cash: shifts.reduce((sum, shift) => sum + Number(shift.cash_collected || 0), 0),
    debt: shifts.reduce((sum, shift) => sum + Number(shift.customer_debt_amount || 0), 0),
})))
const selectedDateSummary = computed(() => dateOptions.value.find(item => item.date === selectedDate.value))
const initialDate = new Date(`${newestDate}T00:00:00`)
const currentMonth = ref(new Date(initialDate.getFullYear(), initialDate.getMonth(), 1))
const calendarCells = computed(() => {
    const first = new Date(currentMonth.value.getFullYear(), currentMonth.value.getMonth(), 1)
    const start = new Date(first)
    start.setDate(1 - ((first.getDay() + 6) % 7))
    return Array.from({ length: 42 }, (_, index) => {
        const date = new Date(start)
        date.setDate(start.getDate() + index)
        const key = keyOf(date)
        return {
            key,
            day: date.getDate(),
            currentMonth: date.getMonth() === currentMonth.value.getMonth(),
            summary: dateOptions.value.find(item => item.date === key),
            disabled: key < cutoffKey || key > todayKey,
        }
    })
})
const monthLabel = computed(() => new Intl.DateTimeFormat('vi-VN', { month: 'long', year: 'numeric' }).format(currentMonth.value))
const canGoPrevious = computed(() => currentMonth.value > new Date(cutoff.getFullYear(), cutoff.getMonth(), 1))
const canGoNext = computed(() => currentMonth.value < new Date(today.getFullYear(), today.getMonth(), 1))
const moveMonth = amount => { currentMonth.value = new Date(currentMonth.value.getFullYear(), currentMonth.value.getMonth() + amount, 1) }
const selectDay = cell => {
    if (cell.disabled) return
    selectedDate.value = cell.key
    calendarOpen.value = false
    expandedShiftId.value = null
    if (!cell.currentMonth) currentMonth.value = new Date(Number(cell.key.slice(0, 4)), Number(cell.key.slice(5, 7)) - 1, 1)
}
watch(dateOptions, () => {
    if (!dateOptions.value.some(item => item.date === selectedDate.value) && dateOptions.value.length) {
        selectedDate.value = dateOptions.value[0].date
    }
}, { deep: true })
const selectDateLabel = computed(() => selectedDate.value ? new Intl.DateTimeFormat('vi-VN', { dateStyle: 'long' }).format(new Date(`${selectedDate.value}T00:00:00`)) : 'Chọn ngày')
const clearFilters = () => { employeeId.value = '' }
const bankAccounts = computed(() => page.props.settings?.bank_accounts || [])
const bankLabel = key => {
    const account = bankAccounts.value.find(item => String(item.id || item.bank_account) === String(key))
    return account ? `${account.bank_name || `BIN ${account.bank_bin}`} · ${account.bank_account}` : String(key)
}
const balanceEntries = balances => Object.entries(balances || {})
const bankTotal = balances => Object.values(balances || {}).reduce((sum, amount) => sum + Number(amount || 0), 0)
const bankDifference = shift => bankTotal(shift.closing_bank_balances) - bankTotal(shift.opening_bank_balances) - bankTotal(shift.bank_collected)
const bankExpected = shift => bankTotal(shift.opening_bank_balances) + bankTotal(shift.bank_collected)
const diffText = difference => difference < 0 ? `Thiếu ${money(Math.abs(difference))}` : difference > 0 ? `Dư ${money(difference)}` : 'Khớp'
const diffPillClass = difference => difference < 0
    ? 'bg-rose-100 text-rose-700'
    : difference > 0
        ? 'bg-amber-100 text-amber-700'
        : 'bg-emerald-100 text-emerald-700'
const cashDifferenceClass = difference => difference < 0
    ? 'border-rose-200 bg-rose-50 text-rose-700'
    : difference > 0
        ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
        : 'border-slate-200 bg-slate-50 text-slate-700'
</script>

<template>
    <div class="space-y-3">
        <PageHeader title="Lịch sử kiểm kê" />

        <section class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
            <div class="grid max-w-4xl items-end gap-2 sm:grid-cols-[minmax(220px,1fr)_minmax(250px,1fr)]">
            <FloatingSelect v-model="employeeId" label="Nhân viên" :options="employees" option-label="name" option-value="id" placeholder-text="Tất cả nhân viên" />
            <div class="flex min-w-0 items-end gap-2">
            <div ref="calendarRoot" class="relative min-w-0 flex-1">
                <label class="absolute -top-2 left-3 z-10 bg-white px-1 text-xs text-slate-500">Ngày xem kết quả</label>
                <button type="button" class="flex h-[42px] w-full items-center justify-between rounded-lg border border-slate-300 bg-white px-3 pt-1 text-left text-sm text-slate-800 hover:bg-slate-50" @click="calendarOpen = !calendarOpen"><span class="truncate">{{ selectDateLabel }}</span><span class="ml-2 shrink-0 text-slate-400">▦</span></button>
                <div v-if="calendarOpen" class="absolute right-0 z-30 mt-2 w-[min(340px,calc(100vw-2.5rem))] overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl">
                    <header class="flex items-center justify-between border-b border-slate-100 px-3 py-2">
                        <button type="button" :disabled="!canGoPrevious" class="rounded-md px-2 py-1 text-sm font-semibold text-slate-600 hover:bg-slate-100 disabled:opacity-30" @click="moveMonth(-1)">‹</button>
                        <h3 class="text-sm font-bold capitalize text-slate-800">{{ monthLabel }}</h3>
                        <button type="button" :disabled="!canGoNext" class="rounded-md px-2 py-1 text-sm font-semibold text-slate-600 hover:bg-slate-100 disabled:opacity-30" @click="moveMonth(1)">›</button>
                    </header>
                    <div class="grid grid-cols-7 bg-slate-50 text-center text-[10px] font-bold text-slate-500"><span v-for="day in ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN']" :key="day" class="py-2">{{ day }}</span></div>
                    <div class="grid grid-cols-7 p-1">
                        <button v-for="cell in calendarCells" :key="cell.key" type="button" :disabled="cell.disabled" class="relative mx-auto my-0.5 flex h-9 w-9 flex-col items-center justify-center rounded-lg text-xs transition disabled:cursor-not-allowed disabled:opacity-25" :class="selectedDate === cell.key ? 'bg-indigo-600 font-bold text-white' : cell.summary ? 'bg-emerald-100 font-bold text-emerald-800 hover:bg-emerald-200' : cell.currentMonth ? 'text-slate-700 hover:bg-slate-100' : 'text-slate-400 hover:bg-slate-100'" @click="selectDay(cell)">{{ cell.day }}<span v-if="cell.summary && selectedDate !== cell.key" class="absolute bottom-0.5 h-1 w-1 rounded-full bg-emerald-600"></span></button>
                    </div>
                    <footer class="flex items-center gap-2 border-t border-slate-100 px-3 py-2 text-[11px] text-slate-500"><span class="h-2.5 w-2.5 rounded bg-emerald-100"></span>Ngày có dữ liệu · Chọn ngày để xem</footer>
                </div>
            </div>
            <button type="button" class="flex h-[42px] shrink-0 items-center gap-1.5 rounded-lg border border-slate-200 px-2.5 text-xs font-semibold text-slate-600 hover:bg-slate-50" title="Xóa bộ lọc nhân viên" aria-label="Xóa bộ lọc nhân viên" @click="clearFilters"><RotateCcw :size="14" /><span class="hidden md:inline">Xóa lọc</span></button>
            </div>
            </div>
            <p class="mt-2 text-xs text-slate-500">{{ dateOptions.length }} ngày có dữ liệu <span class="text-slate-400">· chỉ xem được tối đa 30 ngày gần nhất</span></p>
        </section>

        <section class="min-w-0 space-y-3">
            <div v-if="!selectedShifts.length" class="rounded-2xl border border-dashed border-slate-300 bg-white px-5 py-10 text-center text-sm text-slate-500">
                <Wallet :size="28" class="mx-auto mb-2 text-slate-300" />Không có ca kiểm kê khớp ngày và bộ lọc đã chọn.
            </div>
            <article v-for="shift in selectedShifts" :key="shift.id" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="space-y-3 p-4">
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 border-b border-slate-100 pb-3 text-sm">
                        <strong class="rounded-md bg-indigo-50 px-2 py-0.5 text-indigo-700">Ca {{ shiftNumberById.get(shift.id) }}</strong>
                        <span class="text-slate-700"><span class="text-slate-500">Nhân viên:</span> <strong>{{ shift.user_name || '—' }}</strong></span>
                        <span class="text-slate-600">{{ shift.opened_at }} → {{ shift.closed_at }}</span>
                        <span class="text-slate-700"><span class="text-slate-500">Số đơn:</span> <strong>{{ shift.sale_count }}</strong></span>
                    </div>
                    <!-- Kết quả chính: nhìn là hiểu ca này thu bao nhiêu, còn nợ bao nhiêu -->
                    <div class="grid gap-2 sm:grid-cols-2">
                        <div class="rounded-xl border border-emerald-200/80 bg-emerald-50 px-3 py-2"><p class="text-[11px] text-emerald-700">Tổng thu (tiền mặt + chuyển khoản)</p><strong class="mt-0.5 block text-base text-emerald-800">{{ money(Number(shift.cash_collected || 0) + bankTotal(shift.bank_collected)) }}</strong></div>
                        <div class="rounded-xl border px-3 py-2" :class="Number(shift.customer_debt_amount || 0) > 0 ? 'border-amber-200/80 bg-amber-50' : 'border-slate-200 bg-slate-50'"><p class="text-[11px]" :class="Number(shift.customer_debt_amount || 0) > 0 ? 'text-amber-700' : 'text-slate-500'">Khách còn nợ trong ca</p><strong class="mt-0.5 block text-base" :class="Number(shift.customer_debt_amount || 0) > 0 ? 'text-amber-800' : 'text-slate-700'">{{ money(shift.customer_debt_amount) }}</strong></div>
                    </div>

                    <!-- Đối soát: Đầu ca + Thu trong ca = Dự kiến, so với số kiểm đếm cuối ca -->
                    <div>
                        <h3 class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-500">Đối soát cuối ca</h3>
                        <div class="grid gap-3 lg:grid-cols-2">
                            <section class="rounded-xl border border-slate-200 p-3">
                                <header class="mb-1 flex items-center justify-between gap-2">
                                    <h4 class="text-sm font-bold text-slate-800">Tiền mặt</h4>
                                    <span v-if="shift.difference !== null" class="rounded-full px-2.5 py-0.5 text-xs font-bold" :class="diffPillClass(Number(shift.difference))">{{ diffText(Number(shift.difference)) }}</span>
                                    <span v-else class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-500">Không kiểm đếm</span>
                                </header>
                                <dl class="divide-y divide-slate-100 text-sm">
                                    <div class="flex justify-between py-1.5"><dt class="text-slate-500">Đầu ca</dt><dd class="font-semibold text-slate-800">{{ shift.opening_amount === null ? '—' : money(shift.opening_amount) }}</dd></div>
                                    <div class="flex justify-between py-1.5"><dt class="text-slate-500">Thu trong ca</dt><dd class="font-semibold text-emerald-700">+ {{ money(shift.cash_collected || 0) }}</dd></div>
                                    <div class="flex justify-between py-1.5"><dt class="text-slate-500">Dự kiến cuối ca</dt><dd class="font-semibold text-slate-800">{{ money(shift.expected_cash ?? 0) }}</dd></div>
                                    <div class="flex justify-between py-1.5"><dt class="font-semibold text-slate-700">Kiểm đếm cuối ca</dt><dd class="font-bold text-slate-900">{{ shift.closing_amount === null ? '—' : money(shift.closing_amount) }}</dd></div>
                                </dl>
                            </section>
                            <section class="rounded-xl border border-blue-200/80 bg-blue-50/30 p-3">
                                <header class="mb-1 flex items-center justify-between gap-2">
                                    <h4 class="text-sm font-bold text-blue-800">Chuyển khoản</h4>
                                    <span class="rounded-full px-2.5 py-0.5 text-xs font-bold" :class="diffPillClass(bankDifference(shift))">{{ diffText(bankDifference(shift)) }}</span>
                                </header>
                                <dl class="divide-y divide-blue-100 text-sm">
                                    <div class="flex justify-between py-1.5"><dt class="text-blue-700/80">Đầu ca</dt><dd class="font-semibold text-slate-800">{{ money(bankTotal(shift.opening_bank_balances)) }}</dd></div>
                                    <div class="flex justify-between py-1.5"><dt class="text-blue-700/80">Thu trong ca</dt><dd class="font-semibold text-emerald-700">+ {{ money(bankTotal(shift.bank_collected)) }}</dd></div>
                                    <div class="flex justify-between py-1.5"><dt class="text-blue-700/80">Dự kiến cuối ca</dt><dd class="font-semibold text-slate-800">{{ money(bankExpected(shift)) }}</dd></div>
                                    <div class="flex justify-between py-1.5"><dt class="font-semibold text-blue-800">Số dư cuối ca</dt><dd class="font-bold text-slate-900">{{ money(bankTotal(shift.closing_bank_balances)) }}</dd></div>
                                </dl>
                            </section>
                        </div>
                    </div>
                </div>
                <div class="border-t border-slate-100 px-4 py-2.5">
                    <button type="button" class="text-sm font-semibold text-indigo-700 hover:text-indigo-900" @click="expandedShiftId = expandedShiftId === shift.id ? null : shift.id">{{ expandedShiftId === shift.id ? 'Ẩn chi tiết ca' : shift.closing_amount === null ? 'Xem chi tiết ca' : 'Xem chi tiết số lượng tiền' }}</button>
                </div>

                <div v-if="expandedShiftId === shift.id" class="grid gap-4 border-t border-slate-100 bg-slate-50/50 p-4 xl:grid-cols-2">
                    <div v-if="shift.closing_amount !== null">
                        <h3 class="mb-2 text-sm font-bold text-slate-800">Chi tiết số lượng tiền mặt</h3>
                        <div class="overflow-x-auto rounded-xl border border-slate-200 custom-scrollbar">
                            <table class="app-data-table w-full min-w-[400px] table-fixed text-xs">
                                <thead class="bg-slate-100 text-left text-[10px] uppercase text-slate-500"><tr><th class="w-[27%] px-2 py-2">Mệnh giá</th><th class="w-[16%] px-1 py-2 text-right">SL đầu ca</th><th class="w-[16%] px-1 py-2 text-right">SL cuối ca</th><th class="w-[41%] px-2 py-2 text-right">Thành tiền cuối ca</th></tr></thead>
                                <tbody class="divide-y divide-slate-100"><tr v-for="value in denominations" :key="value"><td class="whitespace-nowrap px-2 py-1.5 font-semibold">{{ money(value) }}</td><td class="px-1 py-1.5 text-right">{{ Number(shift.opening_denominations?.[value] || 0) }}</td><td class="px-1 py-1.5 text-right">{{ Number(shift.closing_denominations?.[value] || 0) }}</td><td class="whitespace-nowrap px-2 py-1.5 text-right font-semibold">{{ money(value * Number(shift.closing_denominations?.[value] || 0)) }}</td></tr></tbody>
                                <tfoot class="bg-slate-50 font-bold"><tr><td colspan="3" class="px-2 py-2 text-right">Tổng kiểm đếm</td><td class="whitespace-nowrap px-2 py-2 text-right">{{ money(shift.closing_amount) }}</td></tr></tfoot>
                            </table>
                        </div>
                    </div>
                    <p v-else class="rounded-xl border border-slate-200 bg-white p-3 text-xs text-slate-600">Ca này không kiểm kê tiền mặt.</p>

                    <div>
                        <h3 class="mb-2 text-sm font-bold text-slate-800">Số dư tài khoản ngân hàng</h3>
                        <div class="overflow-x-auto rounded-xl border border-slate-200 custom-scrollbar">
                            <table class="app-data-table w-full min-w-[360px] table-fixed text-xs">
                                <thead class="bg-slate-100 text-left text-[10px] uppercase text-slate-500"><tr><th class="w-[54%] px-2 py-2">Tài khoản</th><th class="w-[23%] px-1 py-2 text-right">Đầu ca</th><th class="w-[23%] px-1 py-2 text-right">Cuối ca</th></tr></thead>
                                <tbody class="divide-y divide-slate-100"><tr v-for="[key, value] in balanceEntries(shift.closing_bank_balances)" :key="key"><td class="break-words px-2 py-1.5 font-medium">{{ bankLabel(key) }}</td><td class="whitespace-nowrap px-1 py-1.5 text-right">{{ money(shift.opening_bank_balances?.[key]) }}</td><td class="whitespace-nowrap px-1 py-1.5 text-right font-semibold">{{ money(value) }}</td></tr><tr v-if="!balanceEntries(shift.closing_bank_balances).length"><td colspan="3" class="px-2 py-5 text-center text-slate-500">Ca này không ghi nhận số dư ngân hàng.</td></tr></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </article>
        </section>
    </div>
</template>
