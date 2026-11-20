<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { CalendarDays, ChevronLeft, ChevronRight } from 'lucide-vue-next'

// Bộ lọc khoảng ngày — tách từ trang Hóa đơn bán hàng để các trang khác dùng chung.
// v-model:from / v-model:to dạng YYYY-MM-DD; @change phát ra ngay khi người dùng chọn xong khoảng ngày
// (chọn ngày bắt đầu rồi ngày kết thúc, hoặc chọn một khoảng có sẵn) — không cần bấm thêm nút Áp dụng.
const props = defineProps({
    from: { type: String, default: '' },
    to: { type: String, default: '' },
    align: { type: String, default: 'right' }, // right | left — phía mở của lịch
    allowAll: { type: Boolean, default: true }, // cho phép "Toàn thời gian" / Xóa (báo cáo luôn cần một khoảng ngày thì tắt)
    compact: { type: Boolean, default: false }, // nút thấp hơn để đặt cạnh các điều khiển nhỏ (ví dụ biểu đồ ở trang chủ)
})
const emit = defineEmits(['update:from', 'update:to', 'change'])

const allPresetOptions = [
    { label: 'Toàn thời gian', value: 'all' },
    { label: 'Hôm nay', value: 'today' },
    { label: 'Hôm qua', value: 'yesterday' },
    { label: '7 ngày qua', value: 'last_7_days' },
    { label: '30 ngày qua', value: 'last_30_days' },
    { label: 'Tháng này', value: 'this_month' },
    { label: 'Tháng trước', value: 'last_month' },
]

const datePresetOptions = computed(() => props.allowAll ? allPresetOptions : allPresetOptions.filter(preset => preset.value !== 'all'))

const open = ref(false)
const rootRef = ref(null)
const datePreset = ref(props.from || props.to || !props.allowAll ? 'custom' : 'all')
const pickerStart = ref('')
const pickerEnd = ref('')
const calendarMonth = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1))
const calendarView = ref('days') // days | years | months
const yearListRef = ref(null)

const dateInputValue = (date) => {
    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')
    return `${year}-${month}-${day}`
}
const dateFromInput = value => value ? new Date(`${value}T12:00:00`) : null
const formatDate = value => new Intl.DateTimeFormat('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(dateFromInput(value))

const dateRangeFor = (preset) => {
    const today = new Date()
    if (preset === 'all') return { from: '', to: '' }
    if (preset === 'today') {
        const value = dateInputValue(today)
        return { from: value, to: value }
    }
    if (preset === 'yesterday') {
        const yesterday = new Date(today)
        yesterday.setDate(today.getDate() - 1)
        const value = dateInputValue(yesterday)
        return { from: value, to: value }
    }
    if (preset === 'last_7_days') {
        const from = new Date(today)
        from.setDate(today.getDate() - 6)
        return { from: dateInputValue(from), to: dateInputValue(today) }
    }
    if (preset === 'last_30_days') {
        const from = new Date(today)
        from.setDate(today.getDate() - 29)
        return { from: dateInputValue(from), to: dateInputValue(today) }
    }
    if (preset === 'this_month') return { from: dateInputValue(new Date(today.getFullYear(), today.getMonth(), 1)), to: dateInputValue(today) }
    if (preset === 'last_month') return { from: dateInputValue(new Date(today.getFullYear(), today.getMonth() - 1, 1)), to: dateInputValue(new Date(today.getFullYear(), today.getMonth(), 0)) }
    return null
}

// Đang chờ ngày thứ hai để mở rộng thành khoảng ngày (sau khi bấm ngày đầu tiên, bộ lọc đã áp dụng 1 ngày đó).
const awaitingEnd = ref(false)

const commit = (from, to, { close = true } = {}) => {
    emit('update:from', from)
    emit('update:to', to)
    if (close) open.value = false
    emit('change', { from, to })
}

const applyDatePreset = (preset) => {
    awaitingEnd.value = false
    datePreset.value = preset
    const range = dateRangeFor(preset)
    if (!range) return
    commit(range.from, range.to)
}
const monthLabel = month => new Intl.DateTimeFormat('vi-VN', { month: 'long', year: 'numeric' }).format(month)
const yearOptions = computed(() => {
    const current = new Date().getFullYear()
    const selected = calendarMonth.value.getFullYear()
    const start = Math.min(current - 100, selected - 8)
    const end = Math.max(current + 10, selected + 8)
    return Array.from({ length: end - start + 1 }, (_, index) => start + index)
})
const monthOptions = computed(() => Array.from({ length: 12 }, (_, index) => ({
    value: index,
    label: new Intl.DateTimeFormat('vi-VN', { month: 'long' }).format(new Date(2020, index, 1)),
})))
const selectYear = year => {
    calendarMonth.value = new Date(Number(year), calendarMonth.value.getMonth(), 1)
    calendarView.value = 'months'
}
const showYearPicker = async () => {
    calendarView.value = 'years'
    await nextTick()
    const selectedYear = yearListRef.value?.querySelector('[data-selected-year="true"]')
    selectedYear?.scrollIntoView({ block: 'center' })
}
const selectMonth = month => {
    calendarMonth.value = new Date(calendarMonth.value.getFullYear(), Number(month), 1)
    calendarView.value = 'days'
}
const calendarCells = computed(() => {
    const month = calendarMonth.value
    const first = new Date(month.getFullYear(), month.getMonth(), 1)
    const start = new Date(first)
    start.setDate(1 - ((first.getDay() + 6) % 7))
    return Array.from({ length: 42 }, (_, index) => {
        const date = new Date(start)
        date.setDate(start.getDate() + index)
        return { key: dateInputValue(date), day: date.getDate(), currentMonth: date.getMonth() === month.getMonth() }
    })
})
const isPickerSelected = key => key === pickerStart.value || key === pickerEnd.value
const isPickerInRange = key => Boolean(pickerStart.value && pickerEnd.value && key > pickerStart.value && key < pickerEnd.value)
const pickerRangeLabel = computed(() => {
    if (!pickerStart.value) return 'Bấm một ngày để lọc, hoặc bấm hai ngày để lọc khoảng ngày'
    if (awaitingEnd.value) return `${formatDate(pickerStart.value)} · bấm thêm một ngày nữa nếu muốn lọc khoảng ngày`
    return !pickerEnd.value || pickerEnd.value === pickerStart.value ? formatDate(pickerStart.value) : `${formatDate(pickerStart.value)} – ${formatDate(pickerEnd.value)}`
})
const selectedLabel = computed(() => {
    if (!props.from && !props.to) return 'Toàn thời gian'
    if (!props.to || props.to === props.from) return formatDate(props.from || props.to)
    return `${formatDate(props.from)} – ${formatDate(props.to)}`
})

const openPicker = () => {
    awaitingEnd.value = false
    pickerStart.value = props.from
    pickerEnd.value = props.to
    const anchor = dateFromInput(pickerStart.value) || new Date()
    calendarMonth.value = new Date(anchor.getFullYear(), anchor.getMonth(), 1)
    calendarView.value = 'days'
    open.value = true
}
const moveMonth = amount => { calendarMonth.value = new Date(calendarMonth.value.getFullYear(), calendarMonth.value.getMonth() + amount, 1) }
const moveYearPage = amount => {
    const currentIndex = yearOptions.value.indexOf(calendarMonth.value.getFullYear())
    const nextYear = yearOptions.value[Math.max(0, Math.min(yearOptions.value.length - 1, currentIndex + amount * 12))]
    if (nextYear) calendarMonth.value = new Date(nextYear, calendarMonth.value.getMonth(), 1)
}
const selectDate = (key) => {
    datePreset.value = 'custom'

    // Bấm ngày đầu tiên: áp dụng NGAY đúng ngày đó (lọc 1 ngày), lịch vẫn mở để có thể bấm thêm ngày thứ hai.
    if (!awaitingEnd.value) {
        pickerStart.value = key
        pickerEnd.value = key
        awaitingEnd.value = true
        commit(key, key, { close: false })
        return
    }

    // Bấm ngày thứ hai: mở rộng thành khoảng ngày, áp dụng và đóng lịch (bấm lại cùng ngày thì giữ 1 ngày).
    const [from, to] = key < pickerStart.value ? [key, pickerStart.value] : [pickerStart.value, key]
    pickerStart.value = from
    pickerEnd.value = to
    awaitingEnd.value = false
    commit(from, to)
}
const closeOnOutside = event => {
    if (open.value && rootRef.value && !rootRef.value.contains(event.target)) open.value = false
}
onMounted(() => window.addEventListener('pointerdown', closeOnOutside))
onBeforeUnmount(() => window.removeEventListener('pointerdown', closeOnOutside))
</script>

<template>
    <div ref="rootRef" class="relative w-full min-w-0 shrink-0 sm:w-auto">
        <button type="button" class="inline-flex w-full min-w-0 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 text-left text-xs font-semibold text-slate-700 shadow-sm transition hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700 sm:w-auto" :class="compact ? 'h-9 sm:min-w-[190px]' : 'h-[42px] sm:min-w-[220px]'" title="Lọc theo khoảng ngày" @click="open ? open = false : openPicker()">
            <CalendarDays class="h-4 w-4 shrink-0 text-blue-600" />
            <span class="min-w-0 flex-1 truncate">{{ selectedLabel }}</span>
            <ChevronRight class="h-4 w-4 shrink-0 rotate-90 text-slate-400" />
        </button>
        <div v-if="open" class="absolute top-full z-50 mt-2 w-[min(470px,calc(100vw-2rem))] overflow-hidden rounded-2xl border max-sm:fixed max-sm:inset-x-3 max-sm:bottom-3 max-sm:top-auto max-sm:mt-0 max-sm:max-h-[85dvh] max-sm:w-auto max-sm:overflow-y-auto max-sm:shadow-2xl border-slate-200 bg-white shadow-xl" :class="align === 'left' ? 'left-0' : 'right-0'">
            <div class="flex flex-col sm:flex-row">
                <aside class="border-b border-slate-200 bg-slate-50/70 p-2 sm:w-40 sm:shrink-0 sm:border-b-0 sm:border-r">
                    <p class="mb-1 px-2 pt-1 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Khoảng có sẵn</p>
                    <div class="flex flex-wrap gap-1 sm:block sm:space-y-0.5">
                        <button v-for="preset in datePresetOptions" :key="preset.value" type="button" class="w-auto rounded-lg px-2.5 py-1.5 text-left text-[11px] font-semibold transition sm:w-full sm:py-2" :class="datePreset === preset.value ? 'bg-blue-100 text-blue-700' : 'text-slate-600 hover:bg-white hover:text-slate-900'" @click="applyDatePreset(preset.value)">{{ preset.label }}</button>
                    </div>
                </aside>
                <section class="min-w-0 flex-1 p-2.5 sm:p-3">
                    <header class="mb-2 flex items-center justify-between gap-2">
                        <button type="button" class="grid h-10 w-10 place-items-center rounded-lg text-slate-600 transition hover:bg-slate-100 sm:h-8 sm:w-8" :aria-label="calendarView === 'days' ? 'Tháng trước' : 'Năm trước'" @click="calendarView === 'days' ? moveMonth(-1) : moveYearPage(-1)"><ChevronLeft class="h-4 w-4" /></button>
                        <button type="button" class="min-w-0 flex-1 rounded-lg px-2 py-1.5 text-center text-xs font-bold capitalize text-slate-800 transition hover:bg-slate-100" :aria-label="calendarView === 'days' ? 'Chọn năm và tháng' : calendarView === 'years' ? 'Chọn năm' : 'Chọn tháng'" @click="showYearPicker">
                            {{ calendarView === 'years' ? 'Chọn năm' : calendarView === 'months' ? `${calendarMonth.getFullYear()} · Chọn tháng` : monthLabel(calendarMonth) }}
                        </button>
                        <button type="button" class="grid h-10 w-10 place-items-center rounded-lg text-slate-600 transition hover:bg-slate-100 sm:h-8 sm:w-8" :aria-label="calendarView === 'days' ? 'Tháng sau' : 'Năm sau'" @click="calendarView === 'days' ? moveMonth(1) : moveYearPage(1)"><ChevronRight class="h-4 w-4" /></button>
                    </header>
                    <div v-if="calendarView === 'years'" ref="yearListRef" class="grid max-h-64 grid-cols-3 gap-1 overflow-y-auto sm:grid-cols-4">
                        <button v-for="year in yearOptions" :key="year" type="button" :data-selected-year="year === calendarMonth.getFullYear() ? 'true' : 'false'" class="rounded-lg px-2 py-3 text-sm font-semibold transition" :class="year === calendarMonth.getFullYear() ? 'bg-blue-600 text-white' : 'text-slate-700 hover:bg-blue-50 hover:text-blue-700'" @click="selectYear(year)">{{ year }}</button>
                    </div>
                    <div v-else-if="calendarView === 'months'" class="grid grid-cols-3 gap-2 py-2">
                        <button v-for="month in monthOptions" :key="month.value" type="button" class="rounded-lg px-2 py-3 text-xs font-semibold capitalize transition" :class="month.value === calendarMonth.getMonth() ? 'bg-blue-600 text-white' : 'text-slate-700 hover:bg-blue-50 hover:text-blue-700'" @click="selectMonth(month.value)">{{ month.label }}</button>
                    </div>
                    <template v-else>
                    <div class="grid grid-cols-7 text-center text-[10px] font-bold text-slate-400"><span v-for="day in ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN']" :key="day" class="py-1">{{ day }}</span></div>
                    <div class="grid grid-cols-7">
                        <button v-for="cell in calendarCells" :key="cell.key" type="button" class="relative mx-auto my-px grid h-9 w-9 place-items-center sm:h-8 sm:w-8 rounded-md text-[11px] transition" :class="isPickerSelected(cell.key) ? 'bg-blue-600 font-bold text-white shadow-sm hover:bg-blue-700' : isPickerInRange(cell.key) ? 'rounded-none bg-blue-100 text-blue-800 hover:bg-blue-200' : cell.currentMonth ? 'text-slate-700 hover:bg-slate-100' : 'text-slate-400 hover:bg-slate-100'" @click="selectDate(cell.key)">{{ cell.day }}</button>
                    </div>
                    </template>
                </section>
            </div>
            <footer class="flex items-center justify-between gap-2 border-t border-slate-200 bg-slate-50/50 px-3 py-2.5">
                <p class="min-w-0 truncate text-xs font-medium text-slate-600">Đã chọn: <span class="font-bold text-slate-800">{{ pickerRangeLabel }}</span></p>
                <div class="flex shrink-0 items-center gap-1">
                    <button v-if="allowAll" type="button" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-white hover:text-rose-600" @click="applyDatePreset('all')">Xóa</button>
                    <button type="button" class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-bold text-white shadow-sm transition hover:bg-blue-700" @click="open = false">Xong</button>
                </div>
            </footer>
        </div>
    </div>
</template>
